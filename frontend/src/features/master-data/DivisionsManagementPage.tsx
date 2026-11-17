import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import {
  Plus,
  Pencil,
  Trash2,
  Archive,
  Check,
  Building2,
  ChevronDown,
  ChevronRight,
  FolderTree,
} from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import type { Master, Paginated } from "../../lib/api";
import { useAuth } from "../auth/Auth";
import { PageHeading } from "../../components/Layout";
import { State, Modal, Confirm, Field } from "../../components/ui";

const divisionSchema = z.object({
  name: z.string().trim().min(1, "Division name is required.").max(255),
  status: z.number(),
});

const subDivisionSchema = z.object({
  name: z.string().trim().min(1, "Sub-division / unit name is required.").max(255),
  division_id: z.number().min(1, "Parent division is required."),
  status: z.number(),
});

type DivisionFormValues = z.infer<typeof divisionSchema>;
type SubDivisionFormValues = z.infer<typeof subDivisionSchema>;

export function DivisionsManagementPage() {
  const { user } = useAuth();
  const canWrite = user?.role !== "Authorized Viewer";
  const canDelete = user?.role === "Super Admin" || user?.role === "Admin";
  const client = useQueryClient();

  const [expandedDivisions, setExpandedDivisions] = useState<Record<number, boolean>>({});
  const [editingDivision, setEditingDivision] = useState<Master | null | undefined>(undefined);
  const [deletingDivision, setDeletingDivision] = useState<Master | null>(null);

  const [editingSubDivision, setEditingSubDivision] = useState<{
    subDivision: Master | null;
    defaultDivisionId?: number;
  } | undefined>(undefined);
  const [deletingSubDivision, setDeletingSubDivision] = useState<Master | null>(null);

  // Fetch divisions
  const divisionsQuery = useQuery({
    queryKey: ["divisions", "all"],
    queryFn: async () => {
      const res = await api.get<Paginated<Master>>(endpoint("divisions"), {
        params: { per_page: 100 },
      });
      return res.data.data;
    },
  });

  // Fetch all sub-divisions / units
  const unitsQuery = useQuery({
    queryKey: ["units", "all"],
    queryFn: async () => {
      const res = await api.get<Paginated<Master>>(endpoint("units"), {
        params: { per_page: 100 },
      });
      return res.data.data;
    },
  });

  const divisions = divisionsQuery.data ?? [];
  const allUnits = unitsQuery.data ?? [];

  const toggleExpand = (id: number) => {
    setExpandedDivisions((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  // Toggle status for division
  const divisionStatusMutation = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: number }) =>
      api.put(endpoint(`divisions/${id}`), { status }),
    onSuccess: () => {
      toast.success("Division status updated");
      void client.invalidateQueries({ queryKey: ["divisions"] });
      void client.invalidateQueries({ queryKey: ["options", "divisions"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (e) => toast.error(errorMessage(e)),
  });

  // Toggle status for sub-division (unit)
  const subDivisionStatusMutation = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: number }) =>
      api.put(endpoint(`units/${id}`), { status }),
    onSuccess: () => {
      toast.success("Sub-division status updated");
      void client.invalidateQueries({ queryKey: ["units"] });
      void client.invalidateQueries({ queryKey: ["options", "units"] });
    },
    onError: (e) => toast.error(errorMessage(e)),
  });

  // Delete division
  const deleteDivisionMutation = useMutation({
    mutationFn: async (id: number) => api.delete(endpoint(`divisions/${id}`)),
    onSuccess: () => {
      toast.success("Division deleted");
      setDeletingDivision(null);
      void client.invalidateQueries({ queryKey: ["divisions"] });
      void client.invalidateQueries({ queryKey: ["options", "divisions"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (e) => toast.error(Object.values(fieldErrors(e))[0]?.[0] || errorMessage(e)),
  });

  // Delete sub-division
  const deleteSubDivisionMutation = useMutation({
    mutationFn: async (id: number) => api.delete(endpoint(`units/${id}`)),
    onSuccess: () => {
      toast.success("Sub-division deleted");
      setDeletingSubDivision(null);
      void client.invalidateQueries({ queryKey: ["units"] });
      void client.invalidateQueries({ queryKey: ["options", "units"] });
    },
    onError: (e) => toast.error(Object.values(fieldErrors(e))[0]?.[0] || errorMessage(e)),
  });

  const isLoading = divisionsQuery.isLoading || unitsQuery.isLoading;
  const isError = divisionsQuery.isError || unitsQuery.isError;

  return (
    <>
      <PageHeading
        eyebrow="ORGANIZATION STRUCTURE"
        title="Manage Divisions & Sub-Divisions"
        description="Configure Home Affairs main divisions and manage sub-divisions / units nested directly beneath them."
        action={
          canWrite && (
            <div style={{ display: "flex", gap: "8px" }}>
              <button
                className="button secondary"
                onClick={() => setEditingSubDivision({ subDivision: null })}
              >
                <Plus size={16} />
                Add sub-division
              </button>
              <button
                className="button primary"
                onClick={() => setEditingDivision(null)}
              >
                <Plus size={16} />
                Add main division
              </button>
            </div>
          )
        }
      />

      <section className="panel" style={{ padding: 0, overflow: "hidden" }}>
        <div style={{ padding: "20px 24px", borderBottom: "1px solid #e1e7ef", background: "#fbfcfc" }}>
          <h2 style={{ fontSize: "16px", margin: "0 0 4px 0", color: "#064e48" }}>
            Divisional Hierarchy
          </h2>
          <p style={{ margin: 0, fontSize: "13px", color: "#5a716c" }}>
            All operations, attendance records and employee sortings fall under these main divisions and their sub-divisions.
          </p>
        </div>

        {isLoading ? (
          <div style={{ padding: "32px" }}>
            <State title="Loading divisions hierarchy..." loading />
          </div>
        ) : isError ? (
          <div style={{ padding: "32px" }}>
            <State title="Could not load organization data" retry={() => {
              void divisionsQuery.refetch();
              void unitsQuery.refetch();
            }} />
          </div>
        ) : (
          <div className="division-hierarchy-list">
            {divisions.map((div) => {
              const subDivs = allUnits.filter((u) => u.division_id === div.id);
              const isExpanded = expandedDivisions[div.id] ?? true;

              return (
                <div
                  key={div.id}
                  style={{
                    borderBottom: "1px solid #eef2f5",
                    background: div.status === 1 ? "#fff" : "#fafbfb",
                  }}
                >
                  {/* Main Division Header Row */}
                  <div
                    style={{
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "space-between",
                      padding: "16px 24px",
                      background: div.status === 1 ? "#ffffff" : "#f6f8f8",
                    }}
                  >
                    <div
                      style={{
                        display: "flex",
                        alignItems: "center",
                        gap: "12px",
                        cursor: "pointer",
                        flex: 1,
                      }}
                      onClick={() => toggleExpand(div.id)}
                    >
                      <button
                        type="button"
                        className="icon-button"
                        style={{ padding: 4 }}
                        aria-label="Toggle sub-divisions"
                      >
                        {isExpanded ? (
                          <ChevronDown size={18} color="#005a4e" />
                        ) : (
                          <ChevronRight size={18} color="#005a4e" />
                        )}
                      </button>

                      <Building2 size={20} color="#00796b" />

                      <div>
                        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                          <strong style={{ fontSize: "15px", color: "#143734" }}>
                            {div.name}
                          </strong>
                          <span
                            className={`status ${div.status === 1 ? "active" : "neutral"}`}
                            style={{ fontSize: "11px", padding: "2px 8px" }}
                          >
                            {div.status === 1 ? "Active" : "Inactive"}
                          </span>
                        </div>
                        <small style={{ color: "#7a948e", fontSize: "12px" }}>
                          #{div.id} · {subDivs.length} sub-division{subDivs.length === 1 ? "" : "s"}
                        </small>
                      </div>
                    </div>

                    {/* Main Division Actions */}
                    <div className="row-actions" style={{ display: "flex", gap: "6px" }}>
                      {canWrite && (
                        <>
                          <button
                            className="button secondary"
                            style={{ padding: "5px 10px", fontSize: "12px" }}
                            title="Add sub-division to this division"
                            onClick={() =>
                              setEditingSubDivision({
                                subDivision: null,
                                defaultDivisionId: div.id,
                              })
                            }
                          >
                            <Plus size={14} /> Add sub-division
                          </button>
                          <button
                            className="icon-button"
                            aria-label={`Edit ${div.name}`}
                            onClick={() => setEditingDivision(div)}
                          >
                            <Pencil size={15} />
                          </button>
                          <button
                            className="icon-button"
                            aria-label={div.status === 1 ? "Deactivate" : "Activate"}
                            title={div.status === 1 ? "Deactivate" : "Activate"}
                            onClick={() =>
                              divisionStatusMutation.mutate({
                                id: div.id,
                                status: div.status === 1 ? 0 : 1,
                              })
                            }
                          >
                            {div.status === 1 ? <Archive size={15} /> : <Check size={15} />}
                          </button>
                        </>
                      )}
                      {canDelete && (
                        <button
                          className="icon-button delete"
                          aria-label={`Delete ${div.name}`}
                          onClick={() => setDeletingDivision(div)}
                        >
                          <Trash2 size={15} />
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Sub-Divisions Nested Section */}
                  {isExpanded && (
                    <div
                      style={{
                        padding: "8px 24px 16px 54px",
                        background: "#f8fbf9",
                        borderTop: "1px dashed #e2ede8",
                      }}
                    >
                      {subDivs.length === 0 ? (
                        <div
                          style={{
                            padding: "12px 16px",
                            fontSize: "13px",
                            color: "#839c96",
                            fontStyle: "italic",
                          }}
                        >
                          No sub-divisions configured under {div.name}.{" "}
                          {canWrite && (
                            <a
                              href="#add"
                              onClick={(e) => {
                                e.preventDefault();
                                setEditingSubDivision({
                                  subDivision: null,
                                  defaultDivisionId: div.id,
                                });
                              }}
                              style={{ color: "#00796b", fontWeight: 600, marginLeft: 4 }}
                            >
                              + Add one now
                            </a>
                          )}
                        </div>
                      ) : (
                        <div style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
                          {subDivs.map((sub) => (
                            <div
                              key={sub.id}
                              style={{
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "space-between",
                                padding: "8px 14px",
                                background: "#ffffff",
                                border: "1px solid #e1ebe6",
                                borderRadius: "6px",
                              }}
                            >
                              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                                <FolderTree size={15} color="#528b7e" />
                                <span style={{ fontSize: "13.5px", fontWeight: 500, color: "#1c3c37" }}>
                                  {sub.name}
                                </span>
                                <span
                                  className={`status ${sub.status === 1 ? "active" : "neutral"}`}
                                  style={{ fontSize: "10px", padding: "1px 6px" }}
                                >
                                  {sub.status === 1 ? "Active" : "Inactive"}
                                </span>
                              </div>

                              <div style={{ display: "flex", gap: "4px" }}>
                                {canWrite && (
                                  <>
                                    <button
                                      className="icon-button"
                                      aria-label={`Edit ${sub.name}`}
                                      onClick={() =>
                                        setEditingSubDivision({
                                          subDivision: sub,
                                          defaultDivisionId: div.id,
                                        })
                                      }
                                    >
                                      <Pencil size={13} />
                                    </button>
                                    <button
                                      className="icon-button"
                                      aria-label={sub.status === 1 ? "Deactivate" : "Activate"}
                                      title={sub.status === 1 ? "Deactivate" : "Activate"}
                                      onClick={() =>
                                        subDivisionStatusMutation.mutate({
                                          id: sub.id,
                                          status: sub.status === 1 ? 0 : 1,
                                        })
                                      }
                                    >
                                      {sub.status === 1 ? <Archive size={13} /> : <Check size={13} />}
                                    </button>
                                  </>
                                )}
                                {canDelete && (
                                  <button
                                    className="icon-button delete"
                                    aria-label={`Delete ${sub.name}`}
                                    onClick={() => setDeletingSubDivision(sub)}
                                  >
                                    <Trash2 size={13} />
                                  </button>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* Main Division Form Modal */}
      {editingDivision !== undefined && (
        <DivisionModal
          record={editingDivision}
          onClose={() => setEditingDivision(undefined)}
        />
      )}

      {/* Sub-Division Form Modal */}
      {editingSubDivision !== undefined && (
        <SubDivisionModal
          divisions={divisions}
          record={editingSubDivision.subDivision}
          defaultDivisionId={editingSubDivision.defaultDivisionId}
          onClose={() => setEditingSubDivision(undefined)}
        />
      )}

      {/* Confirm Division Deletion */}
      {deletingDivision && (
        <Confirm
          title="Delete this division?"
          description={`Delete “${deletingDivision.name}”? Note: Divisions with assigned employees or sub-divisions cannot be deleted.`}
          busy={deleteDivisionMutation.isPending}
          onClose={() => setDeletingDivision(null)}
          onConfirm={() => deleteDivisionMutation.mutate(deletingDivision.id)}
        />
      )}

      {/* Confirm Sub-Division Deletion */}
      {deletingSubDivision && (
        <Confirm
          title="Delete this sub-division?"
          description={`Delete “${deletingSubDivision.name}”? Note: Sub-divisions with assigned employees cannot be deleted.`}
          busy={deleteSubDivisionMutation.isPending}
          onClose={() => setDeletingSubDivision(null)}
          onConfirm={() => deleteSubDivisionMutation.mutate(deletingSubDivision.id)}
        />
      )}
    </>
  );
}

function DivisionModal({
  record,
  onClose,
}: {
  record: Master | null;
  onClose: () => void;
}) {
  const client = useQueryClient();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<DivisionFormValues>({
    resolver: zodResolver(divisionSchema),
    defaultValues: {
      name: record?.name ?? "",
      status: record?.status ?? 1,
    },
  });

  const mutation = useMutation({
    mutationFn: async (data: DivisionFormValues) =>
      record
        ? api.put(endpoint(`divisions/${record.id}`), data)
        : api.post(endpoint("divisions"), data),
    onSuccess: () => {
      toast.success(record ? "Division updated" : "Division created");
      void client.invalidateQueries({ queryKey: ["divisions"] });
      void client.invalidateQueries({ queryKey: ["options", "divisions"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
      onClose();
    },
    onError: (e) => {
      toast.error(errorMessage(e));
      Object.entries(fieldErrors(e)).forEach(([k, v]) =>
        setError(k as "name", { message: v[0] }),
      );
    },
  });

  return (
    <Modal title={record ? "Edit Main Division" : "Add Main Division"} onClose={onClose}>
      <form onSubmit={handleSubmit((d: DivisionFormValues) => mutation.mutate(d))}>
        <div className="modal-body">
          <Field label="Division Name *" error={errors.name?.message}>
            <input autoFocus {...register("name")} placeholder="e.g. Finance Division" maxLength={255} />
          </Field>
          <Field label="Status" error={errors.status?.message}>
            <select {...register("status", { valueAsNumber: true })}>
              <option value={1}>Active</option>
              <option value={0}>Inactive</option>
            </select>
          </Field>
        </div>
        <div className="modal-actions">
          <button type="button" className="button secondary" onClick={onClose}>
            Cancel
          </button>
          <button disabled={mutation.isPending} className="button primary">
            {mutation.isPending ? "Saving…" : "Save Division"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function SubDivisionModal({
  divisions,
  record,
  defaultDivisionId,
  onClose,
}: {
  divisions: Master[];
  record: Master | null;
  defaultDivisionId?: number;
  onClose: () => void;
}) {
  const client = useQueryClient();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<SubDivisionFormValues>({
    resolver: zodResolver(subDivisionSchema),
    defaultValues: {
      name: record?.name ?? "",
      division_id: record?.division_id ?? defaultDivisionId ?? (divisions[0]?.id || 1),
      status: record?.status ?? 1,
    },
  });

  const mutation = useMutation({
    mutationFn: async (data: SubDivisionFormValues) =>
      record
        ? api.put(endpoint(`units/${record.id}`), data)
        : api.post(endpoint("units"), data),
    onSuccess: () => {
      toast.success(record ? "Sub-division updated" : "Sub-division created");
      void client.invalidateQueries({ queryKey: ["units"] });
      void client.invalidateQueries({ queryKey: ["options", "units"] });
      void client.invalidateQueries({ queryKey: ["employees"] });
      onClose();
    },
    onError: (e) => {
      toast.error(errorMessage(e));
      Object.entries(fieldErrors(e)).forEach(([k, v]) =>
        setError(k as "name", { message: v[0] }),
      );
    },
  });

  return (
    <Modal title={record ? "Edit Sub-Division / Unit" : "Add Sub-Division / Unit"} onClose={onClose}>
      <form onSubmit={handleSubmit((d: SubDivisionFormValues) => mutation.mutate(d))}>
        <div className="modal-body">
          <Field label="Parent Main Division *" error={errors.division_id?.message}>
            <select {...register("division_id", { valueAsNumber: true })}>
              {divisions.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name} {d.status === 0 ? "(Inactive)" : ""}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Sub-Division / Unit Name *" error={errors.name?.message}>
            <input autoFocus {...register("name")} placeholder="e.g. Legal Unit" maxLength={255} />
          </Field>
          <Field label="Status" error={errors.status?.message}>
            <select {...register("status", { valueAsNumber: true })}>
              <option value={1}>Active</option>
              <option value={0}>Inactive</option>
            </select>
          </Field>
        </div>
        <div className="modal-actions">
          <button type="button" className="button secondary" onClick={onClose}>
            Cancel
          </button>
          <button disabled={mutation.isPending} className="button primary">
            {mutation.isPending ? "Saving…" : "Save Sub-Division"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
