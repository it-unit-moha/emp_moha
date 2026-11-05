import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Plus, Search, Pencil, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import type { Master, Paginated } from "../../lib/api";
import { useAuth } from "../auth/Auth";
import { PageHeading } from "../../components/Layout";
import { State, Modal, Confirm, Field, Pagination } from "../../components/ui";
const schema = z.object({
  name: z.string().trim().min(1, "A name is required.").max(255),
});
export function MasterDataPage({
  module,
  title,
  description,
}: {
  module: string;
  title: string;
  description: string;
}) {
  const { user } = useAuth();
  const canWrite = user?.role !== "Authorized Viewer";
  const canDelete = user?.role === "Super Admin" || user?.role === "Admin";
  const client = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Master | null | undefined>(undefined);
  const [deleting, setDeleting] = useState<Master | null>(null);
  const query = useQuery({
    queryKey: [module, search, page],
    queryFn: async () =>
      (
        await api.get<Paginated<Master>>(endpoint(module), {
          params: { search, page },
        })
      ).data,
  });
  const remove = useMutation({
    mutationFn: async (id: number) => api.delete(endpoint(`${module}/${id}`)),
    onSuccess: () => {
      toast.success("Record deleted");
      setDeleting(null);
      void client.invalidateQueries({ queryKey: [module] });
      void client.invalidateQueries({ queryKey: ["options", module] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (e) =>
      toast.error(Object.values(fieldErrors(e))[0]?.[0] || errorMessage(e)),
  });
  return (
    <>
      <PageHeading
        eyebrow="ORGANIZATION"
        title={title}
        description={description}
        action={
          canWrite && (
            <button className="button primary" onClick={() => setEditing(null)}>
              <Plus size={17} />
              Add {title.toLowerCase().replace(/s$/, "")}
            </button>
          )
        }
      />
      <section className="panel">
        <div className="table-toolbar">
          <div>
            <h2>{title} directory</h2>
            <p>Manage your organizational structure</p>
          </div>
          <label className="search">
            <Search size={17} />
            <input
              aria-label={`Search ${title}`}
              placeholder={`Search ${title.toLowerCase()}…`}
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </label>
        </div>
        {query.isPending ? (
          <State title={`Loading ${title.toLowerCase()}`} loading />
        ) : query.isError ? (
          <State
            title="Records could not load"
            error={query.error}
            retry={() => void query.refetch()}
          />
        ) : query.data?.data.length ? (
          <>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Name</th>
                    {module === "divisions" && <th>Status</th>}
                    <th className="text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.data.map((record) => (
                    <tr key={record.id}>
                      <td className="muted">#{record.id}</td>
                      <td className="record-name">
                        {record.name || "Unnamed record"}
                      </td>
                      {module === "divisions" && (
                        <td>
                          <span
                            className={`status ${record.status === 1 ? "active" : "neutral"}`}
                          >
                            {record.status === 1 ? "Active" : "Inactive"}
                          </span>
                        </td>
                      )}
                      <td>
                        <div className="row-actions">
                          {canWrite && (
                            <button
                              className="icon-button"
                              aria-label={`Edit ${record.name}`}
                              onClick={() => setEditing(record)}
                            >
                              <Pencil size={16} />
                            </button>
                          )}
                          {canDelete && (
                            <button
                              className="icon-button delete"
                              aria-label={`Delete ${record.name}`}
                              onClick={() => setDeleting(record)}
                            >
                              <Trash2 size={16} />
                            </button>
                          )}
                          {!canWrite && (
                            <span className="muted">View only</span>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination
              page={query.data.meta.current_page}
              last={query.data.meta.last_page}
              total={query.data.meta.total}
              onChange={setPage}
            />
          </>
        ) : (
          <State
            title="No matching records"
            description="Try another search or add a record to get started."
          />
        )}
      </section>
      {editing !== undefined && (
        <MasterForm
          key={editing?.id ?? "new"}
          module={module}
          record={editing}
          onClose={() => setEditing(undefined)}
        />
      )}
      {deleting && (
        <Confirm
          title="Delete this record?"
          description={`Delete “${deleting.name}”? Records assigned to employees cannot be deleted.`}
          busy={remove.isPending}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove.mutate(deleting.id)}
        />
      )}
    </>
  );
}
function MasterForm({
  module,
  record,
  onClose,
}: {
  module: string;
  record: Master | null;
  onClose: () => void;
}) {
  const client = useQueryClient();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { name: record?.name ?? "" },
  });
  const mutation = useMutation({
    mutationFn: async (data: z.infer<typeof schema>) =>
      record
        ? api.put(endpoint(`${module}/${record.id}`), data)
        : api.post(endpoint(module), data),
    onSuccess: () => {
      toast.success(record ? "Record updated" : "Record created");
      void client.invalidateQueries({ queryKey: [module] });
      void client.invalidateQueries({ queryKey: ["options", module] });
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
    <Modal title={record ? "Edit record" : "Add record"} onClose={onClose}>
      <form onSubmit={handleSubmit((d) => mutation.mutate(d))}>
        <div className="modal-body">
          <Field label="Name *" error={errors.name?.message}>
            <input autoFocus {...register("name")} maxLength={255} />
          </Field>
        </div>
        <div className="modal-actions">
          <button type="button" className="button secondary" onClick={onClose}>
            Cancel
          </button>
          <button disabled={mutation.isPending} className="button primary">
            {mutation.isPending ? "Saving…" : "Save record"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
