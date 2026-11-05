import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Plus, Search, Pencil, Eye, Archive } from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import type { Employee, Master, Paginated } from "../../lib/api";
import { useAuth } from "../auth/Auth";
import { PageHeading } from "../../components/Layout";
import { State, Modal, Confirm, Field, Pagination } from "../../components/ui";

function useOptions(module: string) {
  return useQuery({
    queryKey: ["options", module],
    queryFn: async () => {
      const rows: Master[] = [];
      let page = 1;
      let last = 1;
      do {
        const result = (
          await api.get<Paginated<Master>>(endpoint(module), {
            params: { per_page: 100, page },
          })
        ).data;
        rows.push(...result.data);
        last = result.meta.last_page;
        page++;
      } while (page <= last);
      return rows;
    },
    staleTime: 30000,
  });
}

export function EmployeePage() {
  const { user } = useAuth();
  const canWrite = user?.role !== "Authorized Viewer";
  const client = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({
    division: "",
    unit: "",
    designation: "",
    status: "all",
    sort: "name",
    direction: "asc",
  });
  const [editing, setEditing] = useState<Employee | null | undefined>();
  const [viewing, setViewing] = useState<number | null>(null);
  const [archiving, setArchiving] = useState<Employee | null>(null);
  const divisions = useOptions("divisions");
  const units = useOptions("units");
  const designations = useOptions("designations");
  const query = useQuery({
    queryKey: ["employees", search, page, filters],
    queryFn: async () =>
      (
        await api.get<Paginated<Employee>>(endpoint("employees"), {
          params: {
            search,
            page,
            ...Object.fromEntries(
              Object.entries(filters).filter(([, v]) => v !== ""),
            ),
          },
        })
      ).data,
  });
  const archive = useMutation({
    mutationFn: async (id: number) => api.delete(endpoint(`employees/${id}`)),
    onSuccess: () => {
      toast.success("Employee archived");
      setArchiving(null);
      void client.invalidateQueries({ queryKey: ["employees"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
    },
    onError: (e) => toast.error(errorMessage(e)),
  });
  function filter(key: keyof typeof filters, value: string) {
    setFilters({ ...filters, [key]: value });
    setPage(1);
  }
  return (
    <>
      <PageHeading
        eyebrow="PEOPLE & ORGANIZATION"
        title="Employee Management"
        description="Maintain employee records and organizational assignments."
        action={
          canWrite && (
            <button className="button primary" onClick={() => setEditing(null)}>
              <Plus size={17} />
              Add employee
            </button>
          )
        }
      />
      <section className="panel">
        <div className="table-toolbar">
          <div>
            <h2>Employee directory</h2>
            <p>
              {query.data
                ? `${query.data.meta.total.toLocaleString()} employee records`
                : "Records from the employee register"}
            </p>
          </div>
          <label className="search">
            <Search size={17} />
            <input
              aria-label="Search employees"
              placeholder="Search name, NIC or employee no.…"
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </label>
        </div>
        <div className="filter-bar">
          {(
            [
              { key: "division", label: "divisions", query: divisions },
              { key: "unit", label: "units", query: units },
              {
                key: "designation",
                label: "designations",
                query: designations,
              },
            ] as const
          ).map(({ key, label, query: options }) => (
            <select
              key={key}
              aria-label={`Filter by ${key}`}
              value={filters[key]}
              onChange={(e) => filter(key, e.target.value)}
            >
              <option value="">All {label}</option>
              {options.data?.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name}
                </option>
              ))}
            </select>
          ))}
          <select
            aria-label="Filter by status"
            value={filters.status}
            onChange={(e) => filter("status", e.target.value)}
          >
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
          <select
            aria-label="Sort employees"
            value={filters.sort}
            onChange={(e) => filter("sort", e.target.value)}
          >
            <option value="name">Sort: name</option>
            <option value="emp_no">Sort: employee no.</option>
            <option value="id">Sort: reference</option>
          </select>
          <select
            aria-label="Sort direction"
            value={filters.direction}
            onChange={(e) => filter("direction", e.target.value)}
          >
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
          </select>
        </div>
        {(divisions.isError || units.isError || designations.isError) && (
          <p className="error-banner">
            Some assignment filters could not load. Refresh to try again.
          </p>
        )}
        {query.isPending ? (
          <State title="Loading employee records" loading />
        ) : query.isError ? (
          <State
            title="Employee directory unavailable"
            error={query.error}
            retry={() => void query.refetch()}
          />
        ) : query.data?.data.length ? (
          <>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Employee no. / NIC</th>
                    <th>Division & unit</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th className="text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.data.map((employee) => (
                    <tr key={employee.id}>
                      <td>
                        <div className="employee-cell">
                          <span className="employee-avatar">
                            {employee.name?.slice(0, 1) || "?"}
                          </span>
                          <div>
                            <strong>
                              {employee.name || "Unnamed employee"}
                            </strong>
                            <small>
                              {employee.email || "No email recorded"}
                            </small>
                          </div>
                        </div>
                      </td>
                      <td>
                        {employee.emp_no || "—"}
                        <small className="cell-subtext">
                          {employee.nic || "No NIC recorded"}
                        </small>
                      </td>
                      <td>
                        {employee.division_name || "Unassigned"}
                        <small className="cell-subtext">
                          {employee.unit_name || "No unit assigned"}
                        </small>
                      </td>
                      <td>{employee.designation_name || "Unassigned"}</td>
                      <td>
                        <span
                          className={`status ${employee.is_active === 1 ? "active" : "neutral"}`}
                        >
                          {employee.is_active === 1
                            ? "Active"
                            : employee.is_active === 0
                              ? "Archived"
                              : "Unspecified"}
                        </span>
                      </td>
                      <td>
                        <div className="row-actions">
                          <button
                            className="icon-button"
                            aria-label={`View ${employee.name}`}
                            onClick={() => setViewing(employee.id)}
                          >
                            <Eye size={16} />
                          </button>
                          {canWrite && (
                            <>
                              <button
                                className="icon-button"
                                aria-label={`Edit ${employee.name}`}
                                onClick={() => setEditing(employee)}
                              >
                                <Pencil size={16} />
                              </button>
                              {employee.is_active !== 0 && (
                                <button
                                  className="icon-button delete"
                                  aria-label={`Archive ${employee.name}`}
                                  onClick={() => setArchiving(employee)}
                                >
                                  <Archive size={16} />
                                </button>
                              )}
                            </>
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
            title="No matching employees"
            description="Adjust the search or filters to find an employee."
          />
        )}
      </section>
      {editing !== undefined && (
        <EmployeeForm
          key={editing?.id ?? "new"}
          record={editing}
          onClose={() => setEditing(undefined)}
        />
      )}
      {viewing !== null && (
        <EmployeeDetails id={viewing} onClose={() => setViewing(null)} />
      )}
      {archiving && (
        <Confirm
          title="Archive employee?"
          description={`Archive ${archiving.name}? The imported record will be preserved and marked inactive.`}
          busy={archive.isPending}
          onClose={() => setArchiving(null)}
          onConfirm={() => archive.mutate(archiving.id)}
        />
      )}
    </>
  );
}

const schema = z.object({
  name: z.string().trim().min(1, "Employee name is required.").max(255),
  nic: z.string().max(12),
  emp_no: z.string().max(12),
  email: z.union([z.literal(""), z.string().email().max(35)]),
  contact_no: z.string().max(39),
  address: z.string().max(255),
  dob: z.string(),
  gender: z.string().max(6),
  division: z.string(),
  unit: z.string().max(4),
  designation: z.string(),
});
type Values = z.infer<typeof schema>;
function EmployeeForm({
  record,
  onClose,
}: {
  record: Employee | null;
  onClose: () => void;
}) {
  const client = useQueryClient();
  const divisions = useOptions("divisions");
  const units = useOptions("units");
  const designations = useOptions("designations");
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: record?.name ?? "",
      nic: record?.nic ?? "",
      emp_no: record?.emp_no ?? "",
      email: record?.email ?? "",
      contact_no: record?.contact_no ?? "",
      address: record?.address ?? "",
      dob: record?.dob ?? "",
      gender: record?.gender ?? "",
      division: record?.division?.toString() ?? "",
      unit: record?.unit ?? "",
      designation: record?.designation?.toString() ?? "",
    },
  });
  const mutation = useMutation({
    mutationFn: async (data: Values) => {
      const payload = Object.fromEntries(
        Object.entries(data).map(([k, v]) => [k, v === "" ? null : v]),
      );
      return record
        ? api.put(endpoint(`employees/${record.id}`), payload)
        : api.post(endpoint("employees"), payload);
    },
    onSuccess: () => {
      toast.success(record ? "Employee updated" : "Employee created");
      void client.invalidateQueries({ queryKey: ["employees"] });
      void client.invalidateQueries({ queryKey: ["employee"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
      onClose();
    },
    onError: (e) => {
      toast.error(errorMessage(e));
      Object.entries(fieldErrors(e)).forEach(([k, v]) =>
        setError(k as keyof Values, { message: v[0] }),
      );
    },
  });
  const optionsPending =
    divisions.isPending || units.isPending || designations.isPending;
  const optionsError =
    divisions.isError || units.isError || designations.isError;
  const fields: {
    key: keyof Values;
    label: string;
    type?: string;
    max?: number;
  }[] = [
    { key: "name", label: "Full name *", max: 255 },
    { key: "nic", label: "NIC", max: 12 },
    { key: "emp_no", label: "Employee number", max: 12 },
    { key: "email", label: "Email address", type: "email", max: 35 },
    { key: "contact_no", label: "Contact number", max: 39 },
    { key: "dob", label: "Date of birth", type: "date" },
    { key: "gender", label: "Gender (as recorded)", max: 6 },
    { key: "address", label: "Address", max: 255 },
  ];
  return (
    <Modal title={record ? "Edit employee" : "Add employee"} onClose={onClose}>
      <form onSubmit={handleSubmit((d) => mutation.mutate(d))} noValidate>
        <div className="modal-body">
          <div className="form-section-label">PERSONAL INFORMATION</div>
          <div className="form-grid">
            {fields.map((field) => (
              <Field
                key={field.key}
                label={field.label}
                error={errors[field.key]?.message}
              >
                <input
                  autoFocus={field.key === "name"}
                  type={field.type ?? "text"}
                  maxLength={field.max}
                  {...register(field.key)}
                  aria-invalid={!!errors[field.key]}
                />
              </Field>
            ))}
          </div>
          <div className="form-section-label">ORGANIZATIONAL ASSIGNMENT</div>
          {optionsPending && <p role="status">Loading assignment options…</p>}
          {optionsError && (
            <p className="error-banner">
              Assignment options could not load. Close this dialog and try again
              before saving.
            </p>
          )}
          <div className="form-grid">
            {(
              [
                { key: "division", label: "Division", query: divisions },
                { key: "unit", label: "Unit", query: units },
                {
                  key: "designation",
                  label: "Designation",
                  query: designations,
                },
              ] as const
            ).map(({ key, label, query }) => (
              <Field key={key} label={label} error={errors[key]?.message}>
                <select {...register(key)}>
                  <option value="">Unassigned</option>
                  {query.data?.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </Field>
            ))}
          </div>
          <p className="form-note">
            Category and fingerprint enrollment require a reviewed schema
            extension and are not available in the imported register.
          </p>
        </div>
        <div className="modal-actions">
          <button type="button" className="button secondary" onClick={onClose}>
            Cancel
          </button>
          <button
            className="button primary"
            disabled={mutation.isPending || optionsPending || optionsError}
          >
            {mutation.isPending ? "Saving…" : "Save employee"}
          </button>
        </div>
      </form>
    </Modal>
  );
}
function EmployeeDetails({ id, onClose }: { id: number; onClose: () => void }) {
  const query = useQuery({
    queryKey: ["employee", id],
    queryFn: async () =>
      (await api.get<{ data: Employee }>(endpoint(`employees/${id}`))).data
        .data,
  });
  const d = query.data;
  return (
    <Modal title="Employee details" onClose={onClose}>
      {query.isPending ? (
        <State title="Loading employee" loading />
      ) : query.isError ? (
        <State title="Employee could not load" error={query.error} />
      ) : (
        d && (
          <div className="modal-body">
            <div className="detail-title">
              <span className="employee-avatar">{d.name?.slice(0, 1)}</span>
              <div>
                <h3>{d.name}</h3>
                <p>{d.designation_name || "Designation not assigned"}</p>
              </div>
            </div>
            <dl className="detail-grid">
              {[
                ["Reference", d.id],
                ["Employee number", d.emp_no],
                ["NIC", d.nic],
                ["Email", d.email],
                ["Contact", d.contact_no],
                ["Date of birth", d.dob],
                ["Gender", d.gender],
                ["Address", d.address],
                ["Division", d.division_name],
                ["Unit", d.unit_name],
                [
                  "Status",
                  d.is_active === 1
                    ? "Active"
                    : d.is_active === 0
                      ? "Archived"
                      : "Unspecified",
                ],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value || "Not recorded"}</dd>
                </div>
              ))}
            </dl>
          </div>
        )
      )}
      <div className="modal-actions">
        <button className="button secondary" onClick={onClose}>
          Close
        </button>
      </div>
    </Modal>
  );
}
