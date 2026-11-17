<<<<<<< Updated upstream
import { useState } from "react";
=======
import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import "./employees.css";
>>>>>>> Stashed changes
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
  const [searchParams] = useSearchParams();
  const initialStatus = searchParams.get("status") || "all";
  const client = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState({
    division: "",
    unit: "",
    designation: "",
    status: initialStatus,
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
    if (key === "division") {
      setFilters({ ...filters, division: value, unit: "" });
    } else {
      setFilters({ ...filters, [key]: value });
    }
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
<<<<<<< Updated upstream
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
=======
          <select
            aria-label="Employees per page"
            value={perPage}
            onChange={(e) => {
              setPerPage(Number(e.target.value));
              setPage(1);
            }}
          >
            {[5, 15, 25, 50, 100].map((count) => (
              <option key={count} value={count}>
                Show {count} employees
              </option>
            ))}
          </select>
          {/* Main Division Filter */}
          <select
            aria-label="Filter by division"
            value={filters.division}
            onChange={(e) => filter("division", e.target.value)}
          >
            <option value="">All divisions</option>
            {divisions.data?.filter((d) => d.status === 1).map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>

          {/* Sub-division / Unit Filter: Dynamically filtered by selected division */}
          <select
            aria-label="Filter by sub-division"
            value={filters.unit}
            onChange={(e) => filter("unit", e.target.value)}
            disabled={
              !!filters.division &&
              !units.data?.some(
                (u) =>
                  u.status === 1 &&
                  String(u.division_id) === String(filters.division),
              )
            }
          >
            <option value="">
              {filters.division &&
              !units.data?.some(
                (u) =>
                  u.status === 1 &&
                  String(u.division_id) === String(filters.division),
              )
                ? "No sub-divisions"
                : "All sub-divisions / units"}
            </option>
            {units.data
              ?.filter((u) => {
                if (u.status !== 1) return false;
                if (!filters.division) return true;
                return String(u.division_id) === String(filters.division);
              })
              .map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
>>>>>>> Stashed changes
                </option>
              ))}
          </select>

          {/* Designation Filter */}
          <select
            aria-label="Filter by designation"
            value={filters.designation}
            onChange={(e) => filter("designation", e.target.value)}
          >
            <option value="">All designations</option>
            {designations.data?.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
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
                        <strong style={{ display: "block", color: "#143734", fontWeight: 600 }}>
                          {employee.division_name || "Unassigned"}
                        </strong>
                        {employee.unit_name && (
                          <small
                            className="cell-subtext"
                            style={{
                              display: "block",
                              color: "#6b8a82",
                              fontSize: "11px",
                              fontWeight: 400,
                              marginTop: "2px",
                            }}
                          >
                            {employee.unit_name}
                          </small>
                        )}
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
  service_grade: z.string().max(60),
  enroll_id: z.string().max(40),
  staff_category: z.string(),
  vacation_days: z.string(),
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
      service_grade: record?.service_grade ?? "",
      enroll_id: record?.enroll_id ?? "",
      staff_category: record?.staff_category ?? "",
      vacation_days: record?.vacation_days?.toString() ?? "",
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
<<<<<<< Updated upstream
=======
  const photo = watch("photo")?.[0];
  const selectedDivision = watch("division");
  const filteredUnitsForForm = units.data?.filter((u) => {
    if (u.status !== 1 && u.id !== (record?.unit ? Number(record.unit) : 0)) return false;
    if (!selectedDivision) return true;
    return String(u.division_id) === String(selectedDivision);
  }) ?? [];
>>>>>>> Stashed changes
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
    { key: "emp_no", label: "File / service number", max: 12 },
    { key: "service_grade", label: "Service grade", max: 60 },
    { key: "enroll_id", label: "Fingerprint enrollment ID", max: 40 },
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
<<<<<<< Updated upstream
                </select>
              </Field>
            ))}
=======
                  <Field label="Gender" error={errors.gender?.message}>
                    <select {...register("gender")}>
                      <option value="">Not recorded</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      {record?.gender && !["Male", "Female"].includes(record.gender) && (
                        <option value={record.gender}>{record.gender}</option>
                      )}
                    </select>
                  </Field>
                </div>
              </>
            )}

            {/* ── TAB 2: ASSIGNMENT ── */}
            {activeTab === "assignment" && (
              <>
                {optionsPending && <p role="status">Loading options…</p>}
                {optionsError && (
                  <p className="error-banner">Options could not load. Close and try again.</p>
                )}
                <div className="form-grid">
                  <Field label="Division" error={errors.division?.message}>
                    <select {...register("division")}>
                      <option value="">Select division</option>
                      {divisions.data?.filter((d) => d.status === 1).map((d) => (
                        <option key={d.id} value={d.id}>
                          {d.name}
                        </option>
                      ))}
                    </select>
                  </Field>

                  <Field label="Sub-division / Unit" error={errors.unit?.message}>
                    <select {...register("unit")}>
                      <option value="">
                        {selectedDivision && filteredUnitsForForm.length === 0
                          ? "No sub-divisions for this division"
                          : "Select sub-division"}
                      </option>
                      {filteredUnitsForForm.map((u) => (
                        <option key={u.id} value={u.id}>
                          {u.name}
                        </option>
                      ))}
                    </select>
                  </Field>

                  <Field label="Designation" error={errors.designation?.message}>
                    <select {...register("designation")}>
                      <option value="">Select designation</option>
                      {designations.data?.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.name}
                        </option>
                      ))}
                    </select>
                  </Field>

                  <Field label="Status" error={errors.is_active?.message}>
                    <select {...register("is_active")}>
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                  </Field>
                </div>
              </>
            )}

            {/* ── TAB 3: DOCUMENTS ── */}
            {activeTab === "documents" && (
              <div className="form-grid">
                <Field label="Date of issue" error={errors.date_of_issue?.message}>
                  <input type="date" {...register("date_of_issue")} />
                </Field>
                <Field label="Date of expiry" error={errors.date_of_expiry?.message}>
                  <input type="date" {...register("date_of_expiry")} />
                </Field>
                {(
                  [
                    {
                      key: "application_form" as const,
                      label: "Application form",
                      accept: ".pdf,.jpg,.jpeg,.png",
                      note: "PDF, JPG or PNG, up to 10 MB",
                    },
                    {
                      key: "signature" as const,
                      label: "Applicant signature",
                      accept: ".jpg,.jpeg,.png",
                      note: "JPG or PNG, up to 2 MB",
                    },
                  ]
                ).map(({ key, label, accept, note }) => (
                  <Field key={key} label={label} error={errors[key]?.message}>
                    <input type="file" accept={accept} {...register(key)} />
                    <small>
                      {note}.{" "}
                      {record?.[`has_${key}`]
                        ? "Existing file saved — choose to replace."
                        : "Optional."}
                    </small>
                    {record?.[`has_${key}`] && (
                      <AttachmentDownload employee={record} kind={key} />
                    )}
                  </Field>
                ))}
              </div>
            )}

            {/* ── TAB 4: ATTENDANCE ── */}
            {activeTab === "attendance" && (
              <>
                <p className="form-note">
                  The enrollment ID links this employee to their fingerprint scans. A staff category is required before attendance can be imported.
                </p>
                <div className="form-grid">
                  <Field label="Enrollment ID" error={errors.enroll_id?.message}>
                    <input maxLength={40} {...register("enroll_id")} />
                  </Field>
                  <Field label="Service grade" error={errors.service_grade?.message}>
                    <input maxLength={60} {...register("service_grade")} />
                  </Field>
                  <Field label="Staff category" error={errors.staff_category?.message}>
                    <select {...register("staff_category")}>
                      <option value="">Select staff category</option>
                      <option value="pl">PL Staff (08:00 – 16:45)</option>
                      <option value="other">Other Staff (08:30 – 16:15)</option>
                    </select>
                  </Field>
                  <Field label="Vacation allowance" error={errors.vacation_days?.message}>
                    <select {...register("vacation_days")}>
                      <option value="">Not assigned</option>
                      <option value="24">24 days</option>
                      <option value="28">28 days</option>
                    </select>
                  </Field>
                </div>
              </>
            )}

            {mutation.isError && (
              <p className="error-banner" role="alert">
                {errorMessage(mutation.error)} Check the highlighted fields and try again.
              </p>
            )}
>>>>>>> Stashed changes
          </div>
          <div className="form-grid" style={{ marginTop: 18 }}>
            <Field
              label="Staff category"
              error={errors.staff_category?.message}
            >
              <select {...register("staff_category")}>
                <option value="">Unassigned ? attendance import blocked</option>
                <option value="pl">PL Staff ? 08:00?16:45</option>
                <option value="other">Other Staff ? 08:30?16:15</option>
              </select>
            </Field>
            <Field
              label="Verified vacation allowance"
              error={errors.vacation_days?.message}
            >
              <select {...register("vacation_days")}>
                <option value="">Eligibility not established</option>
                <option value="24">24 days</option>
                <option value="28">28 days</option>
              </select>
            </Field>
          </div>
          <p className="form-note">
            New profiles initialize casual leave at 21 days. Existing annual
            accounts are never overwritten; use an audited balance adjustment
            for verified opening balances or entitlement changes.
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
<<<<<<< Updated upstream
                ["Reference", d.id],
                ["Employee number", d.emp_no],
                ["NIC", d.nic],
                ["Email", d.email],
                ["Contact", d.contact_no],
                ["Date of birth", d.dob],
                ["Gender", d.gender],
                ["Address", d.address],
                ["Service grade", d.service_grade],
                ["Device enrollment ID", d.enroll_id],
                ["Staff category", d.staff_category],
                ["Division", d.division_name],
                ["Unit", d.unit_name],
=======
                ["Application no.", employee.id],
                ["Name with initials", employee.name],
                ["NIC no.", employee.nic],
                ["Address", employee.address],
                ["Phone", employee.contact_no],
                ["Email", employee.email],
                ["Date of birth", employee.dob],
                ["Gender", employee.gender],
                ["Division", employee.division_name],
                ["Sub-division", employee.unit_name],
                ["Designation", employee.designation_name],
                ["Date of issue", employee.date_of_issue],
                ["Date of expiry", employee.date_of_expiry],
                ["Enrollment ID", employee.enroll_id],
                ["Service grade", employee.service_grade],
>>>>>>> Stashed changes
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
