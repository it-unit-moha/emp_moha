import { useState } from "react";
import type { FormEvent, ReactNode } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import {
  ArrowRight,
  CheckCircle2,
  FileDown,
  Upload,
  CalendarDays,
} from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { useAuth } from "../auth/Auth";
import { PageHeading } from "../../components/Layout";
import { Field, State, Confirm } from "../../components/ui";
import "./operations.css";

const today = new Intl.DateTimeFormat("en-CA", {
  timeZone: "Asia/Colombo",
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
}).format(new Date());
const currentYear = Number(today.slice(0, 4));
interface Staff {
  id: number;
  name: string;
  active: number;
  category: string | null;
  enroll_id: string | null;
}
interface Account {
  id: number;
  employee_id: number;
  name: string;
  type: string;
  opening: number;
  balance: number;
}
interface Holiday {
  id: number;
  date: string;
  name: string;
  kind: string;
}
interface Ledger {
  id: number;
  employee_id: number;
  type: string;
  amount: number;
  description: string;
  created_at: string;
}
interface Workflow {
  employees: Staff[];
  unmapped: number;
  balances: Account[];
  ledger: Ledger[];
  holidays: Holiday[];
  deductions_enabled: boolean;
  schedules: Record<string, { start: string; end: string }>;
}
interface Day {
  id: number;
  name: string;
  date: string;
  actual_in: string | null;
  actual_out: string | null;
  required_out: string | null;
  late_minutes: number;
  status: string;
}
interface Batch {
  id: number;
  filename: string;
  inserted: number;
  duplicates: number;
  created_at: string;
}
interface Application {
  id: number;
  employee_id: number;
  name: string;
  type: string;
  scope: string;
  start_date: string;
  end_date: string;
  days: number;
  minutes: number | null;
  reason: string;
  status: string;
}
const label = (value: string) => value.replaceAll("_", " ");
const formValues = (event: FormEvent<HTMLFormElement>) => {
  event.preventDefault();
  return Object.fromEntries(new FormData(event.currentTarget));
};

function useWorkflow(year = currentYear) {
  return useQuery({
    queryKey: ["workflow", year],
    queryFn: async () =>
      (
        await api.get<{ data: Workflow }>(endpoint("workflow"), {
          params: { year },
        })
      ).data.data,
  });
}
function useAccess() {
  const { user } = useAuth();
  return {
    write: user?.role !== "Authorized Viewer",
    admin: user?.role === "Admin" || user?.role === "Super Admin",
  };
}
function useAction() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: async ({
      path,
      body,
      method = "post",
    }: {
      path: string;
      body?: unknown;
      method?: "post" | "delete";
    }) => (await api.request({ url: endpoint(path), method, data: body })).data,
    onSuccess: (data: { message?: string }) => {
      toast.success(data.message ?? "Saved successfully");
      [
        "workflow",
        "attendance",
        "leaves",
        "dashboard",
        "audit",
        "snapshots",
      ].forEach((key) => void client.invalidateQueries({ queryKey: [key] }));
    },
    onError: (error) =>
      toast.error(
        Object.values(fieldErrors(error)).flat()[0] ?? errorMessage(error),
      ),
  });
}
function Feedback({ error }: { error: unknown }) {
  if (!error) return null;
  const details = Object.values(fieldErrors(error)).flat();
  return (
    <div className="error-banner" role="alert">
      {errorMessage(error)}
      {details.length > 0 && (
        <ul>
          {details.map((detail, i) => (
            <li key={i}>{detail}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
function Panel({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="panel">
      <div className="panel-header">
        <h2>{title}</h2>
      </div>
      <div className="operations-body">{children}</div>
    </section>
  );
}
function EmployeeSelect({
  employees,
  all = false,
  value,
  onChange,
}: {
  employees: Staff[];
  all?: boolean;
  value?: string;
  onChange?: (value: string) => void;
}) {
  return (
    <select
      name="employee_id"
      required={!all}
      value={value}
      onChange={(event) => onChange?.(event.target.value)}
      aria-label="Employee"
    >
      <option value="">{all ? "All employees" : "Select employee"}</option>
      {employees
        .filter((e) => all || e.active === 1)
        .map((e) => (
          <option key={e.id} value={e.id}>
            {e.name}
            {!e.category ? " (category needed)" : ""}
          </option>
        ))}
    </select>
  );
}
async function download(path: string, filename: string) {
  try {
    const response = await api.get(endpoint(path), { responseType: "blob" });
    const url = URL.createObjectURL(response.data);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch {
    toast.error(
      "Download failed. Verify the server and reporting data, then try again.",
    );
  }
}

export function SetupPage() {
  const query = useWorkflow();
  const action = useAction();
  const { admin } = useAccess();
  const steps = [
    {
      title: "Organization",
      description:
        "Create divisions, units and designations before assigning employees.",
      path: "/divisions",
    },
    {
      title: "Employee register",
      description:
        "Assign service grade, PL/Other category and a unique fingerprint enrollment ID.",
      path: "/employees",
    },
    {
      title: "Calendar and opening balances",
      description:
        "Configure holidays and initialize the leave year. Verify individual eligibility and opening credits.",
      path: "/holidays",
    },
    {
      title: "Attendance upload",
      description:
        "Use the published CSV format, inspect rejected rows and import verified punches.",
      path: "/attendance",
    },
    {
      title: "Review and leave",
      description:
        "Review missing punches and late covering, submit leave and approve eligible requests.",
      path: "/leaves",
    },
    {
      title: "Reports",
      description:
        "Download monthly attendance, leave utilization and late-warning PDFs.",
      path: "/reports",
    },
  ];
  return (
    <>
      <PageHeading
        eyebrow="START HERE"
        title="Workspace setup"
        description="Follow the operational sequence before processing attendance and leave."
      />
      {query.isError ? (
        <State
          title="Setup could not load"
          error={query.error}
          retry={() => void query.refetch()}
        />
      ) : (
        <>
          <div className="info-banner">
            <CheckCircle2 />
            <div>
              <strong>
                {query.data?.unmapped ?? "…"} active employees still need
                complete enrollment details
              </strong>
              <p>
                Employee records are preserved. Complete their category, device
                enrollment and grade before importing punches.{" "}
                {query.data?.deductions_enabled
                  ? "Automatic late penalties are enabled."
                  : "Automatic penalties remain disabled until the rules are verified."}
              </p>
            </div>
          </div>
          <div className="workflow-steps">
            {steps.map((step, i) => (
              <Link
                className="panel workflow-step"
                to={step.path}
                key={step.title}
              >
                <span className="workflow-number">{i + 1}</span>
                <div>
                  <h2>{step.title}</h2>
                  <p>{step.description}</p>
                </div>
                <ArrowRight size={18} />
              </Link>
            ))}
          </div>
          <Panel title="Initialize the current leave year">
            <p>
              Creates missing accounts only: casual 21 days; vacation 24/28 if
              explicitly assigned. Maternity, paternity and lapsed credits
              require an administrator’s eligibility review. Existing accounts
              are preserved.
            </p>
            {admin && (
              <button
                className="button primary"
                disabled={action.isPending}
                onClick={() =>
                  action.mutate({
                    path: "workflow/initialize",
                    body: { year: currentYear },
                  })
                }
              >
                Initialize {currentYear} accounts
              </button>
            )}
            <Feedback error={action.error} />
          </Panel>
        </>
      )}
    </>
  );
}

export function AttendanceOperationsPage() {
  const [month, setMonth] = useState(today.slice(0, 7));
  const [employee, setEmployee] = useState("");
  const [tab, setTab] = useState("days");
  const workflow = useWorkflow();
  const action = useAction();
  const { write } = useAccess();
  const query = useQuery({
    queryKey: ["attendance", month, employee],
    queryFn: async () =>
      (
        await api.get<{ data: Day[]; imports: Batch[] }>(
          endpoint("attendance"),
          { params: { month, employee_id: employee || undefined } },
        )
      ).data,
  });
  return (
    <>
      <PageHeading
        eyebrow="STEP 4 · ATTENDANCE"
        title="Attendance Management"
        description="Upload verified punches, then review daily lateness, covering and missing punches."
      />
      <div className="operations-toolbar">
        <button
          className={`button ${tab === "days" ? "primary" : "secondary"}`}
          onClick={() => setTab("days")}
        >
          Daily review
        </button>
        <button
          className={`button ${tab === "upload" ? "primary" : "secondary"}`}
          onClick={() => setTab("upload")}
        >
          Upload attendance
        </button>
      </div>
      {tab === "upload" && (
        <Panel title="Upload fingerprint export">
          <div className="info-banner">
            <Upload size={22} />
            <div>
              <strong>Explicit CSV format; no device-code guessing</strong>
              <p>
                Header: enroll_id,timestamp,state. Timestamp: YYYY-MM-DD
                HH:MM:SS in Asia/Colombo. State: IN or OUT. CSV-formatted
                .txt/.dat files are accepted. Convert native device exports to
                this format first.
              </p>
            </div>
          </div>
          <button
            className="button secondary"
            onClick={() =>
              void download("attendance/template", "attendance-template.csv")
            }
          >
            Download CSV header
          </button>
          {write && (
            <form
              className="operations-form"
              onSubmit={(event) => {
                const values = formValues(event);
                const body = new FormData();
                body.append("file", values.file);
                action.mutate({ path: "attendance/import", body });
              }}
            >
              <Field label="Attendance file">
                <input
                  name="file"
                  type="file"
                  accept=".csv,.txt,.dat"
                  required
                />
              </Field>
              <button className="button primary" disabled={action.isPending}>
                {action.isPending
                  ? "Validating and importing…"
                  : "Validate and import"}
              </button>
            </form>
          )}
          <Feedback error={action.error} />
          {action.isSuccess && (
            <p role="status">
              Import result:{" "}
              {JSON.stringify((action.data as { data: unknown }).data)}
            </p>
          )}
          <p className="form-note">
            Invalid rows reject the whole file. Duplicate punches are skipped.
            Daily summaries are recalculated immediately. Missing/out-of-order
            punches and approved-leave conflicts are marked for review; they are
            not automatically penalized.
          </p>
        </Panel>
      )}
      <Panel title="Daily attendance review">
        <div className="operations-toolbar">
          <Field label="Month">
            <input
              type="month"
              value={month}
              onChange={(e) => setMonth(e.target.value)}
              required
            />
          </Field>
          <Field label="Employee">
            <EmployeeSelect
              employees={workflow.data?.employees ?? []}
              all
              value={employee}
              onChange={setEmployee}
            />
          </Field>
        </div>
        {query.isPending ? (
          <State title="Loading punches" loading />
        ) : query.isError ? (
          <State title="Attendance unavailable" error={query.error} />
        ) : (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  {[
                    "Date",
                    "Employee",
                    "IN",
                    "OUT",
                    "Late minutes",
                    "Required OUT",
                    "Result",
                  ].map((h) => (
                    <th key={h}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {query.data?.data.map((day) => (
                  <tr key={day.id}>
                    <td>{day.date}</td>
                    <td>{day.name}</td>
                    <td>{day.actual_in?.slice(11) ?? "Missing"}</td>
                    <td>{day.actual_out?.slice(11) ?? "Missing"}</td>
                    <td>{day.late_minutes}</td>
                    <td>{day.required_out?.slice(11) ?? "Review"}</td>
                    <td>
                      <span
                        className={`status ${day.status === "on_time" || day.status === "late_covered" ? "active" : "neutral"}`}
                      >
                        {label(day.status)}
                      </span>
                    </td>
                  </tr>
                ))}
                {!query.data?.data.length && (
                  <tr>
                    <td colSpan={7}>
                      No recorded attendance for this selection.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
      <Panel title="Import history">
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>File</th>
                <th>Uploaded</th>
                <th>Inserted</th>
                <th>Duplicates</th>
              </tr>
            </thead>
            <tbody>
              {query.data?.imports.map((batch) => (
                <tr key={batch.id}>
                  <td>{batch.filename}</td>
                  <td>{batch.created_at}</td>
                  <td>{batch.inserted}</td>
                  <td>{batch.duplicates}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </>
  );
}

export function HolidaysPage() {
  const [year, setYear] = useState(currentYear);
  const query = useWorkflow(year);
  const action = useAction();
  const { admin } = useAccess();
  const [removing, setRemoving] = useState<Holiday | null>(null);
  return (
    <>
      <PageHeading
        eyebrow="STEP 3 · BUSINESS CALENDAR"
        title="Public holidays"
        description="Public and mercantile dates are excluded from domestic leave and lateness penalties."
      />
      <Panel title="Holiday calendar">
        <Field label="Year">
          <input
            type="number"
            min="2000"
            max="2100"
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
          />
        </Field>
        {admin && (
          <form
            className="operations-form form-grid"
            onSubmit={(event) => {
              const form = event.currentTarget;
              const body = formValues(event);
              action.mutate(
                { path: "holidays", body },
                { onSuccess: () => form.reset() },
              );
            }}
          >
            <Field label="Date">
              <input type="date" name="date" required />
            </Field>
            <Field label="Holiday name">
              <input name="name" maxLength={255} required />
            </Field>
            <Field label="Type">
              <select name="kind">
                <option value="public">Public</option>
                <option value="mercantile">Mercantile</option>
              </select>
            </Field>
            <button className="button primary" disabled={action.isPending}>
              Add holiday
            </button>
          </form>
        )}
        <Feedback error={action.error} />
        {query.isError && (
          <State title="Calendar unavailable" error={query.error} />
        )}
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Name</th>
                <th>Type</th>
                {admin && <th>Action</th>}
              </tr>
            </thead>
            <tbody>
              {query.data?.holidays.map((h) => (
                <tr key={h.id}>
                  <td>{h.date}</td>
                  <td>{h.name}</td>
                  <td>{h.kind}</td>
                  {admin && (
                    <td>
                      <button
                        className="button secondary"
                        onClick={() => setRemoving(h)}
                      >
                        Remove
                      </button>
                    </td>
                  )}
                </tr>
              ))}
              {!query.data?.holidays.length && (
                <tr>
                  <td colSpan={4}>
                    No holidays configured for {year}. Add the verified calendar
                    before leave approval.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <p className="form-note">
          Holiday changes recalculate recorded attendance and enabled penalties.
          Cancel affected leave approvals before changing their calendar dates,
          then resubmit and approve them against the corrected calendar.
        </p>
      </Panel>
      {removing && (
        <Confirm
          title="Remove holiday?"
          description={removing.name}
          busy={action.isPending}
          onClose={() => setRemoving(null)}
          onConfirm={() =>
            action.mutate(
              { path: `holidays/${removing.id}`, method: "delete" },
              { onSuccess: () => setRemoving(null) },
            )
          }
        />
      )}
    </>
  );
}

export function LeaveOperationsPage() {
  const [year, setYear] = useState(currentYear);
  const [employee, setEmployee] = useState("");
  const [type, setType] = useState("casual");
  const [halfDay, setHalfDay] = useState(false);
  const workflow = useWorkflow(year);
  const action = useAction();
  const { write, admin } = useAccess();
  const query = useQuery({
    queryKey: ["leaves", year],
    queryFn: async () =>
      (
        await api.get<{ data: Application[] }>(endpoint("leaves"), {
          params: { year },
        })
      ).data.data,
  });
  const filtered = (workflow.data?.balances ?? []).filter(
    (a) => !employee || a.employee_id === Number(employee),
  );
  return (
    <>
      <PageHeading
        eyebrow="STEP 5 · LEAVE AND REVIEW"
        title="Leave Management"
        description="Submit requests, review eligibility and keep every balance change in the ledger."
      />
      <div className="operations-toolbar">
        <Field label="Leave year">
          <input
            type="number"
            min="2000"
            max="2100"
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
          />
        </Field>
        <Field label="Filter employee">
          <EmployeeSelect
            employees={workflow.data?.employees ?? []}
            all
            value={employee}
            onChange={setEmployee}
          />
        </Field>
      </div>
      {write && (
        <Panel title="New leave application">
          <form
            className="form-grid operations-form"
            onSubmit={(event) => {
              const body = formValues(event);
              action.mutate(
                {
                  path: "leaves",
                  body: {
                    ...body,
                    minutes: body.minutes || null,
                    half_day: body.half_day === "on",
                  },
                },
                {
                  onError: (error) => {
                    if (fieldErrors(error).short_leave_limit) {
                      setType("casual");
                      setHalfDay(true);
                      toast.info(
                        "A half-day casual request is prepared. Review it and submit when ready.",
                      );
                    }
                  },
                },
              );
            }}
          >
            <Field label="Employee">
              <EmployeeSelect employees={workflow.data?.employees ?? []} />
            </Field>
            <Field label="Leave type">
              <select
                name="type"
                value={type}
                onChange={(e) => setType(e.target.value)}
              >
                {[
                  "casual",
                  "vacation",
                  "maternity",
                  "paternity",
                  "lapsed",
                  "short_morning",
                  "short_afternoon",
                ].map((t) => (
                  <option key={t} value={t}>
                    {label(t)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Start date">
              <input
                name="start_date"
                type="date"
                defaultValue={today}
                required
              />
            </Field>
            <Field label="End date">
              <input
                name="end_date"
                type="date"
                defaultValue={today}
                required
              />
            </Field>
            <Field label="Counting rules">
              <select name="scope">
                <option value="domestic">Domestic — working days only</option>
                <option value="foreign">
                  Foreign vacation — all calendar days
                </option>
              </select>
            </Field>
            {type.startsWith("short_") ? (
              <Field label="Duration in minutes (maximum 90)">
                <input name="minutes" type="number" min="1" max="90" required />
              </Field>
            ) : type === "casual" ? (
              <label className="operations-checkbox">
                <input
                  type="checkbox"
                  name="half_day"
                  checked={halfDay}
                  onChange={(event) => setHalfDay(event.target.checked)}
                />
                Half-day casual leave
              </label>
            ) : null}
            <Field label="Reason">
              <input name="reason" maxLength={1000} required />
            </Field>
            <button className="button primary" disabled={action.isPending}>
              {action.isPending ? "Saving…" : "Submit for approval"}
            </button>
          </form>
          <Feedback error={action.error} />
          <p className="form-note">
            Short leave: Other Staff only, one working day, max 90 minutes, max
            two pending/approved requests per month. For a third request, select
            casual leave and half day. Exact short-leave windows and supporting
            eligibility remain part of administrator review.
          </p>
        </Panel>
      )}
      <Panel title="Applications and approval queue">
        {query.isError ? (
          <State title="Applications unavailable" error={query.error} />
        ) : (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  {[
                    "Employee",
                    "Type",
                    "Dates",
                    "Duration",
                    "Status",
                    "Reason",
                    ...(admin ? ["Review"] : []),
                  ].map((h) => (
                    <th key={h}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {query.data
                  ?.filter(
                    (a) => !employee || a.employee_id === Number(employee),
                  )
                  .map((a) => (
                    <tr key={a.id}>
                      <td>{a.name}</td>
                      <td>
                        {label(a.type)}
                        <small className="cell-subtext">{a.scope}</small>
                      </td>
                      <td>
                        {a.start_date}
                        <br />
                        {a.end_date}
                      </td>
                      <td>
                        {a.minutes ? `${a.minutes} min` : `${a.days} days`}
                      </td>
                      <td>
                        <span
                          className={`status ${a.status === "approved" ? "active" : "neutral"}`}
                        >
                          {a.status}
                        </span>
                      </td>
                      <td>{a.reason}</td>
                      {admin && (
                        <td>
                          {a.status === "pending" && (
                            <div className="row-actions">
                              <button
                                className="button primary"
                                disabled={action.isPending}
                                onClick={() =>
                                  action.mutate({
                                    path: `leaves/${a.id}/review`,
                                    body: { status: "approved" },
                                  })
                                }
                              >
                                Approve
                              </button>
                              <button
                                className="button secondary"
                                disabled={action.isPending}
                                onClick={() =>
                                  action.mutate({
                                    path: `leaves/${a.id}/review`,
                                    body: { status: "rejected" },
                                  })
                                }
                              >
                                Reject
                              </button>
                            </div>
                          )}
                          {a.status === "approved" && (
                            <button
                              className="button secondary"
                              disabled={action.isPending}
                              onClick={() =>
                                action.mutate({
                                  path: `leaves/${a.id}/review`,
                                  body: { status: "cancelled" },
                                })
                              }
                            >
                              Cancel approval
                            </button>
                          )}
                        </td>
                      )}
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
      <Panel title="Leave balances">
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Annual opening</th>
                <th>Available</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((a) => (
                <tr key={a.id}>
                  <td>{a.name}</td>
                  <td>{a.type}</td>
                  <td>{a.opening}</td>
                  <td>{a.balance}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {admin && (
          <button
            className="button secondary"
            disabled={action.isPending}
            onClick={() =>
              action.mutate({ path: "workflow/initialize", body: { year } })
            }
          >
            Initialize missing {year} accounts
          </button>
        )}
      </Panel>
      {admin && (
        <Panel title="Eligibility and opening-credit adjustment">
          <p>
            Use verified opening balances or documented accrual/lapse decisions.
            Enter a positive credit or negative adjustment; a reason is
            required. Every entry is audited.
          </p>
          <form
            className="form-grid operations-form"
            onSubmit={(event) => {
              const body = formValues(event);
              action.mutate({
                path: "leave-balances/adjust",
                body: { ...body, year },
              });
            }}
          >
            <Field label="Employee">
              <EmployeeSelect employees={workflow.data?.employees ?? []} />
            </Field>
            <Field label="Account">
              <select name="type">
                {["casual", "vacation", "maternity", "paternity", "lapsed"].map(
                  (t) => (
                    <option key={t}>{t}</option>
                  ),
                )}
              </select>
            </Field>
            <Field label="Adjustment in days">
              <input
                type="number"
                name="amount"
                min="-366"
                max="366"
                step="0.5"
                required
              />
            </Field>
            <Field label="Reason / approval reference">
              <input name="reason" maxLength={255} required />
            </Field>
            <button className="button primary" disabled={action.isPending}>
              Record adjustment
            </button>
          </form>
        </Panel>
      )}
      <Panel title="Ledger history">
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Posted</th>
                <th>Employee</th>
                <th>Account</th>
                <th>Days</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              {workflow.data?.ledger
                .filter((l) => !employee || l.employee_id === Number(employee))
                .map((l) => (
                  <tr key={l.id}>
                    <td>{l.created_at}</td>
                    <td>
                      {
                        workflow.data?.employees.find(
                          (e) => e.id === l.employee_id,
                        )?.name
                      }
                    </td>
                    <td>{l.type}</td>
                    <td>{l.amount}</td>
                    <td>{l.description}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </>
  );
}

export function ReportsOperationsPage() {
  const [month, setMonth] = useState(today.slice(0, 7));
  const [employee, setEmployee] = useState("");
  const [busy, setBusy] = useState(false);
  const workflow = useWorkflow();
  const snapshots = useQuery({
    queryKey: ["snapshots"],
    queryFn: async () =>
      (
        await api.get<{
          data: { id: number; month: string; created_at: string }[];
        }>(endpoint("reports/snapshots"))
      ).data.data,
  });
  return (
    <>
      <PageHeading
        eyebrow="STEP 6 · REPORTING"
        title="Reports"
        description="Server-generated PDF attendance sheets, leave utilization and late-warning reports."
      />
      <Panel title="Monthly report filters">
        <div className="operations-toolbar">
          <Field label="Reporting month">
            <input
              type="month"
              required
              value={month}
              onChange={(e) => setMonth(e.target.value)}
            />
          </Field>
          <Field label="Employee">
            <EmployeeSelect
              employees={workflow.data?.employees ?? []}
              all
              value={employee}
              onChange={setEmployee}
            />
          </Field>
        </div>
      </Panel>
      <div className="workflow-steps">
        {[
          {
            type: "attendance",
            title: "Monthly attendance sheet",
            description:
              "IN/OUT, required departure, lateness, covering, short leave and penalty entries.",
          },
          {
            type: "leave-utilization",
            title: "Leave utilization",
            description:
              "Department-wise opening, credited, utilized and remaining balances on ledger posted dates.",
          },
          {
            type: "late-warning",
            title: "Late penalty warning",
            description:
              "Employees with three or more uncovered late days in the selected month.",
          },
        ].map((report) => (
          <Panel key={report.type} title={report.title}>
            <p>{report.description}</p>
            <button
              className="button primary"
              disabled={busy || !month}
              onClick={async () => {
                setBusy(true);
                await download(
                  `reports/pdf?type=${report.type}&month=${month}${employee ? `&employee_id=${employee}` : ""}`,
                  `galms-${report.type}-${month}.pdf`,
                );
                setBusy(false);
              }}
            >
              <FileDown size={17} />
              Download PDF
            </button>
          </Panel>
        ))}
      </div>
      <Panel title="Scheduled warning snapshots">
        <p>
          The monthly warning job is scheduled for the 25th at 09:00
          Asia/Colombo. The server must run Laravel’s scheduler.
        </p>
        {snapshots.isError && (
          <State title="Snapshots unavailable" error={snapshots.error} />
        )}
        <div className="operations-toolbar">
          {snapshots.data?.map((report) => (
            <button
              className="button secondary"
              key={report.id}
              onClick={() =>
                void download(
                  `reports/snapshots/${report.id}`,
                  `late-warning-${report.month}.pdf`,
                )
              }
            >
              <CalendarDays size={16} />
              {report.month} snapshot
            </button>
          ))}
        </div>
      </Panel>
    </>
  );
}

export function AuditPage() {
  const query = useQuery({
    queryKey: ["audit"],
    queryFn: async () =>
      (
        await api.get<{
          data: {
            id: number;
            created_at: string;
            actor_id: number | null;
            action: string;
            entity: string;
            entity_id: number | null;
            details: string;
          }[];
        }>(endpoint("audit"))
      ).data.data,
  });
  return (
    <>
      <PageHeading
        eyebrow="ADMINISTRATION"
        title="Audit history"
        description="Recent employee, attendance, leave and calendar actions."
      />
      <Panel title="Latest 200 actions">
        {query.isError ? (
          <State title="Audit history unavailable" error={query.error} />
        ) : (
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Actor ID</th>
                  <th>Action</th>
                  <th>Entity</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                {query.data?.map((row) => (
                  <tr key={row.id}>
                    <td>{row.created_at}</td>
                    <td>{row.actor_id ?? "System"}</td>
                    <td>{label(row.action)}</td>
                    <td>
                      {row.entity} #{row.entity_id}
                    </td>
                    <td>{row.details}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </>
  );
}

export function PolicySettingsPage() {
  const workflow = useWorkflow();
  const action = useAction();
  const { admin } = useAccess();
  return (
    <>
      <PageHeading
        eyebrow="ADMINISTRATION ? RULES"
        title="Settings"
        description="Review the operating schedules and explicitly authorize the late-penalty policy."
      />
      <Panel title="Staff schedules">
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th>Start</th>
                <th>End</th>
                <th>Clock duration</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>PL Staff</td>
                <td>08:00</td>
                <td>16:45</td>
                <td>8 hours 45 minutes</td>
              </tr>
              <tr>
                <td>Other Staff</td>
                <td>08:30</td>
                <td>16:15</td>
                <td>7 hours 45 minutes</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p>
          Late minutes are the positive difference between actual IN and the
          category start time. Required OUT is the standard end plus late
          minutes, on the same day. No unspecified grace period is silently
          applied.
        </p>
      </Panel>
      <Panel title="Automatic late penalties">
        <p>
          Current status:{" "}
          <strong>
            {workflow.data?.deductions_enabled ? "Enabled" : "Disabled"}
          </strong>
          . Each three uncovered late days in a calendar month produces a
          half-day casual deduction. Corrections and holiday changes post
          compensating entries. A negative resulting balance must be reviewed by
          the administrator.
        </p>
        <p>
          Missing punches and approved-leave conflicts are excluded for review.
          Confirm the grace period, transport duty exceptions and applicable
          policy before enabling this on real data. Disabling reverses automated
          penalty entries without deleting their history.
        </p>
        {admin && (
          <form
            className="operations-form"
            onSubmit={(event) => {
              const values = formValues(event);
              action.mutate({
                path: "settings/penalties",
                body: {
                  enabled: values.enabled === "true",
                  confirm_rules: values.confirm_rules === "on",
                },
              });
            }}
          >
            <Field label="Penalty policy">
              <select name="enabled">
                <option value="false">Disable automatic deductions</option>
                <option value="true">
                  Enable SRS three-late / half-day rule
                </option>
              </select>
            </Field>
            <label className="operations-checkbox">
              <input name="confirm_rules" type="checkbox" required />I have
              reviewed the applicable rules and the effect on existing records.
            </label>
            <button className="button primary" disabled={action.isPending}>
              Save and reconcile
            </button>
          </form>
        )}
        <Feedback error={action.error} />
      </Panel>
      <Panel title="Operational setup">
        <p>
          Manage verified holiday dates and opening-credit decisions before
          processing leave.
        </p>
        <div className="operations-toolbar">
          <Link className="button secondary" to="/holidays">
            Holiday calendar
          </Link>
          <Link className="button secondary" to="/leaves">
            Balances and eligibility
          </Link>
          <Link className="button secondary" to="/audit">
            Audit history
          </Link>
          <Link className="button secondary" to="/setup">
            Workflow checklist
          </Link>
        </div>
      </Panel>
    </>
  );
}
