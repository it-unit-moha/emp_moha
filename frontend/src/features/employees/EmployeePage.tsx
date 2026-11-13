import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import "./employees.css";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  Plus,
  Search,
  Pencil,
  Eye,
  Archive,
  CalendarCheck,
  Download,
} from "lucide-react";
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
  const [perPage, setPerPage] = useState(15);
  const [showPersonal, setShowPersonal] = useState(false);
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
    queryKey: ["employees", search, page, perPage, filters],
    queryFn: async () =>
      (
        await api.get<Paginated<Employee>>(endpoint("employees"), {
          params: {
            search,
            page,
            per_page: perPage,
            ...Object.fromEntries(
              Object.entries(filters).filter(([, v]) => v !== ""),
            ),
          },
        })
      ).data,
  });
  // Status change (activate / deactivate) mutation
  const statusChange = useMutation({
    // payload: { id, is_active, inactive_reason? }
    mutationFn: async (payload: { id: number; is_active: boolean; inactive_reason?: string }) =>
      api.patch(`employees/${payload.id}/status`, {
        is_active: payload.is_active,
        ...(payload.is_active ? {} : { inactive_reason: payload.inactive_reason }),
      }),
    onSuccess: () => {
      toast.success("Employee status updated");
      setArchiving(null);
      void client.invalidateQueries({ queryKey: ["employees"] });
      void client.invalidateQueries({ queryKey: ["dashboard"] });
      void client.invalidateQueries({ queryKey: ["attendance-workspace"] });
      void client.invalidateQueries({ queryKey: ["workflow"] });
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
        title="Employee register"
        description="Find an employee, view their details, or update their record."
        action={
          canWrite && (
            <button className="button primary" onClick={() => setEditing(null)}>
              <Plus size={17} />
              Add new employee
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
        <div className="employee-directory-options">
          <label>
            <input
              type="checkbox"
              checked={showPersonal}
              onChange={(e) => setShowPersonal(e.target.checked)}
            />{" "}
            Show contact and personal details
          </label>
        </div>
        <div className="filter-bar">
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
            <option value="archived">Inactive</option>
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
                    <th>Application no.</th>
                    <th>Employee</th>
                    <th>Employee no. / NIC</th>
                    <th>Division & unit</th>
                    <th>Designation</th>
                    {showPersonal && (
                      <>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Date of birth</th>
                      </>
                    )}
                    <th>Status</th>
                    <th className="text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {query.data.data.map((employee) => (
                    <tr key={employee.id}>
                      <td>{employee.id}</td>
                      <td>
                        <div className="employee-cell">
                          <EmployeePhoto
                            key={`${employee.id}-${query.dataUpdatedAt}`}
                            employee={employee}
                          />
                          <div>
                            <button
                              type="button"
                              className="record-name employee-name-button"
                              onClick={() => setViewing(employee.id)}
                            >
                              {employee.name || "Unnamed employee"}
                            </button>
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
                      {showPersonal && (
                        <>
                          <td>{employee.address || "Not recorded"}</td>
                          <td>{employee.contact_no || "Not recorded"}</td>
                          <td>{employee.gender || "Not recorded"}</td>
                          <td>{employee.dob || "Not recorded"}</td>
                        </>
                      )}
                      <td>
                        <span
                          className={`status ${employee.is_active === 1 ? "active" : "neutral"}`}
                        >
                          {employee.is_active === 1
                            ? "Active"
                            : employee.is_active === 0
                              ? "Inactive"
                              : "Unspecified"}
                        </span>
                      </td>
                      <td>
                        <div className="row-actions">
                          <button
                            className="button secondary"
                            title="View employee details"
                            aria-label={`View ${employee.name}`}
                            onClick={() => setViewing(employee.id)}
                          >
                            <Eye size={16} /> View
                          </button>
                          {canWrite && (
                            <>
                              <button
                                className="button secondary"
                                title="Edit this employee"
                                aria-label={`Edit ${employee.name}`}
                                onClick={() => setEditing(employee)}
                              >
                                <Pencil size={16} /> Edit
                              </button>
                              {employee.is_active !== 0 && (
                                <button
                                  className="icon-button delete"
                                  title="Mark employee inactive"
                                  aria-label={`Mark ${employee.name} inactive`}
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
        <EmployeeDetails
          id={viewing}
          onClose={() => setViewing(null)}
          onEdit={
            canWrite
              ? (employee) => {
                  setViewing(null);
                  setEditing(employee);
                }
              : undefined
          }
        />
      )}
      {archiving && (
        <Confirm
          title="Mark employee inactive?"
          description={`Mark ${archiving.name} inactive? Their records will be kept. You can reactivate them by editing their status.`}
          busy={statusChange.isPending}
          onClose={() => setArchiving(null)}
          onConfirm={() => {
            const reason = prompt('Provide a reason for de‑activating this employee:', '');
            if (!reason?.trim()) {
              alert('A reason is required to deactivate.');
              return;
            }
            statusChange.mutate({ id: archiving.id, is_active: false, inactive_reason: reason });
          }}
        />
      )}
    </>
  );
}

const uploadField = (maxMB: number) =>
  z
    .custom<FileList>()
    .optional()
    .refine(
      (files) => !files?.length || files[0].size <= maxMB * 1024 * 1024,
      `Choose a file no larger than ${maxMB} MB.`,
    );
const schema = z
  .object({
    name: z.string().trim().min(1, "Name with initials is required.").max(255),
    nic: z.string().max(12),
    emp_no: z.string().max(12),
    address: z.string().max(255),
    contact_no: z.string().max(39),
    email: z.union([z.literal(""), z.string().email().max(35)]),
    dob: z.string(),
    gender: z.string().max(6),
    designation: z.string(),
    unit: z.string().max(4),
    division: z.string(),
    date_of_issue: z.string(),
    date_of_expiry: z.string(),
    is_active: z.enum(["0", "1"]),
    service_grade: z.string().max(60),
    enroll_id: z.string().max(40),
    staff_category: z.string(),
    vacation_days: z.string(),
    application_form: uploadField(10),
    signature: uploadField(2),
    photo: uploadField(2),
  })
  .refine(
    (values) =>
      !values.date_of_issue ||
      !values.date_of_expiry ||
      values.date_of_expiry >= values.date_of_issue,
    {
      path: ["date_of_expiry"],
      message: "Expiry must be on or after the issue date.",
    },
  );
type Values = z.infer<typeof schema>;
type AttachmentKind = "application_form" | "signature" | "photo";

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
  const [activeTab, setActiveTab] = useState<"personal" | "assignment" | "documents" | "attendance">("personal");

  // Determine if any tab has errors to show indicator
  const personalKeys = ["name", "nic", "address", "contact_no", "email", "dob", "emp_no", "gender", "photo"] as const;
  const assignmentKeys = ["designation", "unit", "division", "is_active"] as const;
  const documentKeys = ["date_of_issue", "date_of_expiry", "application_form", "signature"] as const;
  const attendanceKeys = ["enroll_id", "service_grade", "staff_category", "vacation_days"] as const;

  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(
      // Photo is only required for brand new employees
      record
        ? schema
        : schema.refine((data) => !!data.photo?.length, {
            path: ["photo"],
            message: "Add an employee photo before saving this record.",
          }),
    ),
    defaultValues: {
      name: record?.name ?? "",
      nic: record?.nic ?? "",
      emp_no: record?.emp_no ?? "",
      address: record?.address ?? "",
      contact_no: record?.contact_no ?? "",
      email: record?.email ?? "",
      dob: record?.dob ?? "",
      gender: record?.gender ?? "",
      designation: record?.designation?.toString() ?? "",
      unit: record?.unit ?? "",
      division: record?.division?.toString() ?? "",
      date_of_issue: record?.date_of_issue ?? "",
      date_of_expiry: record?.date_of_expiry ?? "",
      is_active: record?.is_active === 0 ? "0" : "1",
      service_grade: record?.service_grade ?? "",
      enroll_id: record?.enroll_id ?? "",
      staff_category: record?.staff_category ?? "",
      vacation_days: record?.vacation_days?.toString() ?? "",
    },
  });
  const photo = watch("photo")?.[0];
  const mutation = useMutation({
    mutationFn: async (data: Values) => {
      const body = new FormData();
      Object.entries(data).forEach(([key, value]) => {
        if (["application_form", "signature", "photo"].includes(key)) {
          const file = (value as FileList | undefined)?.[0];
          if (file) body.append(key, file);
        } else body.append(key, String(value ?? ""));
      });
      if (record) body.append("_method", "PUT");
      return api.post(
        endpoint(record ? `employees/${record.id}` : "employees"),
        body,
      );
    },
    onSuccess: () => {
      toast.success(record ? "Employee updated" : "Employee created");
      [
        "employees",
        "employee",
        "dashboard",
        "attendance-workspace",
        "workflow",
      ].forEach((key) => void client.invalidateQueries({ queryKey: [key] }));
      onClose();
    },
    onError: (error) => {
      toast.error(errorMessage(error));
      Object.entries(fieldErrors(error)).forEach(([key, messages]) =>
        setError(key as keyof Values, { message: messages[0] }),
      );
    },
  });
  const optionsPending =
    divisions.isPending || units.isPending || designations.isPending;
  const optionsError =
    divisions.isError || units.isError || designations.isError;

  const hasPersonalError = personalKeys.some((k) => errors[k]);
  const hasAssignmentError = assignmentKeys.some((k) => errors[k]);
  const hasDocumentError = documentKeys.some((k) => errors[k]);
  const hasAttendanceError = attendanceKeys.some((k) => errors[k]);

  const tabs = [
    { id: "personal" as const, label: "Personal", hasError: hasPersonalError },
    { id: "assignment" as const, label: "Assignment", hasError: hasAssignmentError },
    { id: "documents" as const, label: "Documents", hasError: hasDocumentError },
    { id: "attendance" as const, label: "Attendance", hasError: hasAttendanceError },
  ];

  const personal: {
    key: "name" | "nic" | "address" | "contact_no" | "email" | "dob" | "emp_no";
    label: string;
    type?: string;
    max?: number;
  }[] = [
    { key: "name", label: "Name with initials *", max: 255 },
    { key: "nic", label: "NIC no.", max: 12 },
    { key: "emp_no", label: "Employee no.", max: 12 },
    { key: "contact_no", label: "Phone", type: "tel", max: 39 },
    { key: "email", label: "Email", type: "email", max: 35 },
    { key: "dob", label: "Date of birth", type: "date" },
    { key: "address", label: "Address", max: 255 },
  ];
  return (
    <Modal
      title={record ? `Edit — ${record.name}` : "Add new employee"}
      onClose={onClose}
    >
      <form onSubmit={handleSubmit((data) => mutation.mutate(data))} noValidate>
        <fieldset disabled={mutation.isPending} className="employee-form-fields">
          {/* Tab navigation */}
          <div className="emp-form-tabs" role="tablist">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                type="button"
                role="tab"
                aria-selected={activeTab === tab.id}
                className={`emp-form-tab ${activeTab === tab.id ? "active" : ""} ${tab.hasError ? "has-error" : ""}`}
                onClick={() => setActiveTab(tab.id)}
              >
                {tab.label}
                {tab.hasError && <span className="tab-error-dot" aria-label="has errors" />}
              </button>
            ))}
          </div>

          <div className="modal-body emp-form-body">
            {/* ── TAB 1: PERSONAL ── */}
            {activeTab === "personal" && (
              <>
                {/* Photo row — always visible on personal tab */}
                <section className="employee-photo-section" aria-label="Employee photo">
                  <div className="employee-photo-preview">
                    {photo ? (
                      <SelectedPhoto file={photo} />
                    ) : record?.has_photo ? (
                      <EmployeePhoto employee={record} large />
                    ) : (
                      <span className="employee-profile-placeholder">Photo</span>
                    )}
                  </div>
                  <div className="employee-photo-controls">
                    <h3>{record ? "Employee photo" : "Add employee photo *"}</h3>
                    <p>
                      {record
                        ? record.has_photo
                          ? "Current photo shown. Choose a new image to replace it (optional)."
                          : "No photo on file. You can add one now or update it later."
                        : "Choose a clear photo. Required for new employees."}
                    </p>
                    <Field
                      label={record ? "Replace photo (optional)" : "Upload photo *"}
                      error={errors.photo?.message}
                    >
                      <input type="file" accept=".jpg,.jpeg,.png" {...register("photo")} />
                    </Field>
                    <small>JPG or PNG, up to 2 MB.{photo ? " New photo selected." : ""}</small>
                  </div>
                </section>

                <div className="form-grid">
                  {personal.map((field) => (
                    <Field key={field.key} label={field.label} error={errors[field.key]?.message}>
                      <input
                        autoFocus={field.key === "name"}
                        type={field.type ?? "text"}
                        maxLength={field.max}
                        {...register(field.key)}
                        aria-invalid={!!errors[field.key]}
                      />
                    </Field>
                  ))}
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
                  {(
                    [
                      { key: "designation", label: "Designation", query: designations },
                      { key: "unit", label: "Unit", query: units },
                      { key: "division", label: "Division", query: divisions },
                    ] as const
                  ).map(({ key, label, query }) => (
                    <Field key={key} label={label} error={errors[key]?.message}>
                      <select {...register(key)}>
                        <option value="">Select {label.toLowerCase()}</option>
                        {query.data?.map((option) => (
                          <option key={option.id} value={option.id}>
                            {option.name}
                          </option>
                        ))}
                      </select>
                    </Field>
                  ))}
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
          </div>

          <div className="modal-actions emp-form-actions">
            {/* Tab navigation arrows for keyboard users */}
            <div className="emp-form-nav">
              {activeTab !== "personal" && (
                <button
                  type="button"
                  className="button secondary"
                  onClick={() => {
                    const idx = tabs.findIndex((t) => t.id === activeTab);
                    setActiveTab(tabs[idx - 1].id);
                  }}
                >
                  ← Back
                </button>
              )}
              {activeTab !== "attendance" && (
                <button
                  type="button"
                  className="button secondary"
                  onClick={() => {
                    const idx = tabs.findIndex((t) => t.id === activeTab);
                    setActiveTab(tabs[idx + 1].id);
                  }}
                >
                  Next →
                </button>
              )}
            </div>
            <div className="emp-form-submit">
              <button type="button" className="button secondary" onClick={onClose}>
                Cancel
              </button>
              <button
                className="button primary"
                disabled={mutation.isPending || optionsPending || optionsError}
              >
                {mutation.isPending
                  ? "Saving…"
                  : record
                    ? "Save changes"
                    : "Create employee"}
              </button>
            </div>
          </div>
        </fieldset>
      </form>
    </Modal>
  );
}

function EmployeePhoto({
  employee,
  large = false,
}: {
  employee: Employee;
  large?: boolean;
}) {
  const [url, setUrl] = useState("");
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    let active = true;
    let objectUrl = "";
    setUrl("");
    setFailed(false);
    if (employee.has_photo) {
      api
        .get(endpoint(`employees/${employee.id}/files/photo`), {
          responseType: "blob",
        })
        .then((response) => {
          if (active) {
            objectUrl = URL.createObjectURL(response.data);
            setUrl(objectUrl);
          }
        })
        .catch(() => {
          if (active) setFailed(true);
        });
    }
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [employee.id, employee.has_photo]);
  return url ? (
    <img
      className={large ? "employee-profile-photo" : "employee-list-photo"}
      src={url}
      alt={`${employee.name}'s photo`}
    />
  ) : (
    <span
      className={large ? "employee-profile-placeholder" : "employee-avatar"}
      aria-label={failed ? "Photo unavailable" : "No photo"}
    >
      {employee.name?.slice(0, 1) || "?"}
    </span>
  );
}
function SelectedPhoto({ file }: { file: File }) {
  const [url, setUrl] = useState("");
  useEffect(() => {
    const next = URL.createObjectURL(file);
    setUrl(next);
    return () => URL.revokeObjectURL(next);
  }, [file]);
  return url ? (
    <img
      className="employee-profile-photo"
      src={url}
      alt="Selected employee photo"
    />
  ) : null;
}
function AttachmentDownload({
  employee,
  kind,
}: {
  employee: Employee;
  kind: AttachmentKind;
}) {
  const [busy, setBusy] = useState(false);
  const download = async () => {
    setBusy(true);
    try {
      const response = await api.get<Blob>(
        endpoint(`employees/${employee.id}/files/${kind}`),
        { responseType: "blob" },
      );
      const url = URL.createObjectURL(response.data);
      const extension = response.data.type.includes("pdf")
        ? "pdf"
        : response.data.type.includes("png")
          ? "png"
          : "jpg";
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = `employee-${employee.id}-${kind}.${extension}`;
      anchor.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(false);
    }
  };
  return (
    <button
      type="button"
      className="button secondary employee-document-link"
      disabled={busy}
      onClick={() => void download()}
    >
      <Download size={15} />
      {busy ? "Downloading..." : `Download ${kind.replaceAll("_", " ")}`}
    </button>
  );
}
function EmployeeDetails({
  id,
  onClose,
  onEdit,
}: {
  id: number;
  onClose: () => void;
  onEdit?: (employee: Employee) => void;
}) {
  const query = useQuery({
    queryKey: ["employee", id],
    queryFn: async () =>
      (await api.get<{ data: Employee }>(endpoint(`employees/${id}`))).data
        .data,
  });
  const employee = query.data;
  return (
    <Modal title="Employee details" onClose={onClose}>
      {query.isPending ? (
        <State title="Loading employee" loading />
      ) : query.isError ? (
        <State
          title="Employee could not load"
          error={query.error}
          retry={() => void query.refetch()}
        />
      ) : (
        employee && (
          <div className="modal-body">
            <div className="detail-title">
              <EmployeePhoto employee={employee} large />
              <div>
                <h3>{employee.name}</h3>
                <p>Employee no. {employee.emp_no || "Not recorded"}</p>
                <span
                  className={`status ${employee.is_active === 1 ? "active" : "neutral"}`}
                >
                  {employee.is_active === 1
                    ? "Active"
                    : employee.is_active === 0
                      ? "Inactive"
                      : "Not recorded"}
                </span>
              </div>
            </div>
            <dl className="detail-grid">
              {[
                ["Application no.", employee.id],
                ["Name with initials", employee.name],
                ["NIC no.", employee.nic],
                ["Address", employee.address],
                ["Phone", employee.contact_no],
                ["Email", employee.email],
                ["Date of birth", employee.dob],
                ["Gender", employee.gender],
                ["Designation", employee.designation_name],
                ["Unit", employee.unit_name],
                ["Division", employee.division_name],
                ["Date of issue", employee.date_of_issue],
                ["Date of expiry", employee.date_of_expiry],
                ["Enrollment ID", employee.enroll_id],
                ["Service grade", employee.service_grade],
                [
                  "Staff category",
                  employee.staff_category === "pl"
                    ? "PL Staff"
                    : employee.staff_category === "other"
                      ? "Other Staff"
                      : null,
                ],
              ].map(([label, value]) => (
                <div key={label}>
                  <dt>{label}</dt>
                  <dd>{value || "Not recorded"}</dd>
                </div>
              ))}
            </dl>
            <div className="form-section-label">DOCUMENTS</div>
            <div className="employee-documents">
              {(["application_form", "signature", "photo"] as const).map(
                (kind) =>
                  employee[`has_${kind}`] ? (
                    <AttachmentDownload
                      key={kind}
                      employee={employee}
                      kind={kind}
                    />
                  ) : (
                    <span key={kind} className="muted">
                      {kind.replaceAll("_", " ")}: not uploaded
                    </span>
                  ),
              )}
            </div>
          </div>
        )
      )}
      <div className="modal-actions">
        <button className="button secondary" onClick={onClose}>
          Close
        </button>
        {employee && (
          <Link
            className="button secondary"
            to={`/attendance?employee_id=${employee.id}${employee.division ? `&division_id=${employee.division}` : ""}`}
          >
            <CalendarCheck size={16} />
            Attendance history
          </Link>
        )}
        {employee && onEdit && (
          <button className="button primary" onClick={() => onEdit(employee)}>
            <Pencil size={16} />
            Edit employee
          </button>
        )}
      </div>
    </Modal>
  );
}
