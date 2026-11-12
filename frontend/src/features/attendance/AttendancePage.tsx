import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileDown, RefreshCw, ArrowRight, Building2 } from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { PageHeading } from "../../components/Layout";
import { Field, State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
import "./attendance.css";
import { AttendanceTabs } from "./AttendanceUploadPage";

const today = new Intl.DateTimeFormat("en-CA", {
  timeZone: "Asia/Colombo",
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
}).format(new Date());
interface Summary {
  recorded: number;
  late: number;
  covered: number;
  short_leave: number;
  half_days: number;
  review: number;
}
interface Person {
  id: number;
  name: string;
  emp_no: string | null;
  division_id: number;
  division_name: string | null;
  designation_name: string | null;
  unit_name: string | null;
  email: string | null;
  staff_category: string | null;
  service_grade: string | null;
  enroll_id: string | null;
  is_active: number;
  summary: Summary;
}
interface Day {
  date: string;
  actual_in: string | null;
  actual_out: string | null;
  late_minutes: number;
  early_minutes?: number;
  required_out: string | null;
  status?: string;
  evaluation: string;
  remarks: string;
}
interface Workspace {
  divisions: { id: number; division: string; employees: number }[];
  employees: Person[];
  days: Day[];
  imports: {
    id: number;
    filename: string;
    inserted: number;
    created_at: string;
  }[];
}
const words = (s: string) => s.replaceAll("_", " ");
const clock = (s: string | null) => (s ? s.slice(11, 16) : "—");
export function AttendancePage({ directory = false }: { directory?: boolean }) {
  const [params, setParams] = useSearchParams();
  const { user } = useAuth();
  const write = user?.role !== "Authorized Viewer";
  const month = params.get("month") || today.slice(0, 7);
  const division = params.get("division_id") || "";
  const employee = params.get("employee_id") || "";
  const search = params.get("search") || "";
  const date = params.get("date") || "";
  const client = useQueryClient();

  const [busy, setBusy] = useState(false);

  const update = (values: Record<string, string>) =>
    setParams((previous) => {
      const next = new URLSearchParams(previous);
      Object.entries(values).forEach(([k, v]) =>
        v ? next.set(k, v) : next.delete(k),
      );
      return next;
    });
  const query = useQuery({
    queryKey: ["attendance-workspace", month, division, employee, search, date],
    queryFn: async () =>
      (
        await api.get<Workspace>(endpoint("attendance/workspace"), {
          params: {
            month,
            division_id: division || undefined,
            employee_id: employee || undefined,
            search: search || undefined,
            date: date || undefined,
          },
        })
      ).data,
  });
  const mutation = useMutation({
    mutationFn: async () =>
      (await api.post(endpoint("attendance/recalculate"), { month })).data,
    onSuccess: (result) => {
      toast.success(
        result.message ||
          `${result.data.inserted} punches imported; affected months evaluated automatically.`,
      );

      [
        "attendance-workspace",
        "attendance",
        "dashboard",
        "workflow",
        "audit",
      ].forEach((key) => void client.invalidateQueries({ queryKey: [key] }));
    },
    onError: (e) =>
      toast.error(Object.values(fieldErrors(e))[0]?.[0] || errorMessage(e)),
  });
  const person = employee ? query.data?.employees[0] : undefined;
  const selectedDivision = query.data?.divisions.find(
    (d) => String(d.id) === division,
  );
  const summary = query.data?.employees.reduce(
    (sum, p) => {
      (Object.keys(sum) as (keyof Summary)[]).forEach(
        (k) => (sum[k] += p.summary[k]),
      );
      return sum;
    },
    {
      recorded: 0,
      late: 0,
      covered: 0,
      short_leave: 0,
      half_days: 0,
      review: 0,
    },
  );
  const pdf = async (type = "attendance") => {
    setBusy(true);
    try {
      const r = await api.get(endpoint("reports/pdf"), {
        params: {
          type,
          month,
          division_id: division || undefined,
          employee_id: employee || undefined,
        },
        responseType: "blob",
      });
      const url = URL.createObjectURL(r.data);
      const a = document.createElement("a");
      a.href = url;
      a.download = `galms-${type}-${month}${employee ? `-employee-${employee}` : division ? `-division-${division}` : ""}.pdf`;
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
      toast.error(errorMessage(e));
    } finally {
      setBusy(false);
    }
  };
  const directoryView = directory && !division && !employee && !search;
  return (
    <>
      <PageHeading
        eyebrow="ATTENDANCE WORKSPACE"
        title={
          person?.name ||
          selectedDivision?.division ||
          (directory ? "Divisions" : "Attendance & history")
        }
        description="Browse divisions, find an employee, and review attendance for any month."
        action={
          <button
            className="button primary"
            disabled={busy}
            onClick={() => void pdf()}
          >
            <FileDown size={17} />
            {employee ? "Employee monthly PDF" : "Monthly attendance PDF"}
          </button>
        }
      />
      {!directory && !employee && <AttendanceTabs />}
      <section className="panel attendance-filters">
        <div className="attendance-breadcrumb">
          <Link to={`/divisions?month=${month}`}>Divisions</Link>
          <span>/</span>
          <Link
            to={`/attendance?month=${month}${division ? `&division_id=${division}` : ""}`}
          >
            {selectedDivision?.division || "All employees"}
          </Link>
          {employee && (
            <>
              <span>/</span>
              <span>{person?.name || "Employee"}</span>
            </>
          )}
        </div>
        <div className="attendance-filter-grid">
          <Field label="Month">
            <input
              type="month"
              value={month}
              required
              onChange={(e) => {
                if (e.target.value) update({ month: e.target.value, date: "" });
              }}
            />
          </Field>
          <Field label="Calendar date (optional)">
            <input
              type="date"
              value={date}
              onChange={(e) =>
                update({
                  date: e.target.value,
                  ...(e.target.value
                    ? { month: e.target.value.slice(0, 7) }
                    : {}),
                })
              }
            />
          </Field>
          <Field label="Division · official order">
            <select
              value={division}
              onChange={(e) =>
                update({ division_id: e.target.value, employee_id: "" })
              }
            >
              <option value="">All divisions</option>
              {query.data?.divisions.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.division}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Search employee">
            <input
              placeholder="Name, service no. or enrollment ID"
              value={search}
              onChange={(e) =>
                update({ search: e.target.value, employee_id: "" })
              }
            />
          </Field>
        </div>
        <div className="attendance-actions">
          <button
            className="button secondary"
            onClick={() =>
              update({ date: "", search: "", employee_id: "", division_id: "" })
            }
          >
            Clear filters
          </button>
          {!employee && (
            <button
              className="button secondary"
              disabled={busy}
              onClick={() => void pdf("divisional")}
            >
              <FileDown size={16} />
              Monthly divisional summary
            </button>
          )}
          {write && (
            <button
              className="button secondary"
              disabled={mutation.isPending}
              onClick={() => mutation.mutate()}
            >
              <RefreshCw size={16} />
              Re-evaluate {month}
            </button>
          )}
          <Link className="button secondary" to="/divisions/manage">
            Manage divisions
          </Link>
        </div>
      </section>
      {query.isPending ? (
        <State title="Loading monthly attendance" loading />
      ) : query.isError ? (
        <State
          title="Attendance could not load"
          error={query.error}
          retry={() => void query.refetch()}
        />
      ) : (
        <>
          {directoryView ? (
            <section
              className="division-directory"
              aria-label="Division directory"
            >
              {query.data.divisions.map((d, i) => (
                <Link
                  key={d.id}
                  className="panel division-card"
                  to={`/divisions?month=${month}&division_id=${d.id}`}
                >
                  <span className="division-number">
                    {String(i + 1).padStart(2, "0")}
                  </span>
                  <Building2 size={25} />
                  <h2>{d.division}</h2>
                  <p>{d.employees} employee records</p>
                  <span className="division-open">
                    View employees & attendance <ArrowRight size={17} />
                  </span>
                </Link>
              ))}
            </section>
          ) : (
            <>
              {person && (
                <section className="panel employee-attendance-profile">
                  <h2>{person.name}</h2>
                  <dl>
                    {[
                      ["Service number", person.emp_no],
                      ["Division", person.division_name],
                      ["Designation", person.designation_name],
                      ["Unit", person.unit_name],
                      [
                        "Staff category",
                        person.staff_category === "pl"
                          ? "PL Staff (OES / Drivers)"
                          : person.staff_category === "other"
                            ? "Other Staff"
                            : "Not assigned",
                      ],
                      ["Grade", person.service_grade],
                      ["Enrollment ID", person.enroll_id],
                      ["Email", person.email],
                    ].map(([k, v]) => (
                      <div key={k}>
                        <dt>{k}</dt>
                        <dd>{v || "—"}</dd>
                      </div>
                    ))}
                  </dl>
                </section>
              )}
              <div className="attendance-summary">
                {[
                  ["Recorded days", summary?.recorded],
                  ["Late arrivals", summary?.late],
                  ["Late covered", summary?.covered],
                  ["Short leaves", summary?.short_leave],
                  ["Half-day conversions", summary?.half_days],
                  ["Needs review", summary?.review],
                ].map(([title, n]) => (
                  <div className="panel" key={title}>
                    <span>{title}</span>
                    <strong>{n ?? 0}</strong>
                  </div>
                ))}
              </div>
              <section className="panel">
                <div className="table-toolbar">
                  <div>
                    <h2>
                      {employee
                        ? "Daily attendance & evaluation"
                        : "Employees · division order"}
                    </h2>
                    <p>
                      {month}
                      {date ? ` · ${date}` : " · full month"} · Open an employee
                      for attendance history and their PDF report.
                    </p>
                  </div>
                </div>
                <div className="table-scroll">
                  <table>
                    <thead>
                      <tr>
                        {(employee
                          ? [
                              "Date",
                              "IN",
                              "OUT",
                              "Late min.",
                              "Early min.",
                              "Required OUT",
                              "Evaluation",
                              "Remarks",
                            ]
                          : [
                              "Employee",
                              "Service no.",
                              "Division / designation",
                              "Recorded",
                              "Late / covered",
                              "Short leave",
                              "Half days",
                              "History",
                            ]
                        ).map((h) => (
                          <th key={h}>{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {employee
                        ? query.data.days.map((d) => (
                            <tr key={d.date}>
                              <td>{d.date}</td>
                              <td>{clock(d.actual_in)}</td>
                              <td>{clock(d.actual_out)}</td>
                              <td>{d.late_minutes}</td>
                              <td>{d.early_minutes || 0}</td>
                              <td>{clock(d.required_out)}</td>
                              <td>
                                <span
                                  className={`status ${d.evaluation === "half_day" || d.evaluation?.includes("review") ? "neutral" : "active"}`}
                                >
                                  {words(d.evaluation || d.status || "review")}
                                </span>
                              </td>
                              <td className="attendance-remarks">
                                {d.remarks}
                              </td>
                            </tr>
                          ))
                        : query.data.employees.map((p) => (
                            <tr key={p.id}>
                              <td>
                                <Link
                                  className="record-name"
                                  to={`/attendance?month=${month}&employee_id=${p.id}${p.division_id ? `&division_id=${p.division_id}` : ""}`}
                                >
                                  {p.name}
                                </Link>
                                {p.is_active !== 1 && (
                                  <small> · Archived</small>
                                )}
                              </td>
                              <td>{p.emp_no || "—"}</td>
                              <td>
                                {p.division_name || "Unassigned"}
                                <small className="attendance-secondary">
                                  {p.designation_name || "—"}
                                </small>
                              </td>
                              <td>{p.summary.recorded}</td>
                              <td>
                                {p.summary.late} / {p.summary.covered}
                              </td>
                              <td>{p.summary.short_leave}</td>
                              <td>{p.summary.half_days}</td>
                              <td>
                                <Link
                                  className="button secondary"
                                  to={`/attendance?month=${month}&employee_id=${p.id}${p.division_id ? `&division_id=${p.division_id}` : ""}`}
                                >
                                  View attendance <ArrowRight size={15} />
                                </Link>
                              </td>
                            </tr>
                          ))}
                    </tbody>
                  </table>
                </div>
                {!query.data.employees.length && (
                  <State
                    title="No matching employees"
                    description="Try another search or division. This division may not have assigned employees yet."
                  />
                )}
              </section>
            </>
          )}
        </>
      )}
      <section className="panel attendance-policy">
        <h2>Automatic daily evaluation</h2>
        <p>
          PL Staff: 08:00–16:45. Other Staff: 08:30–16:15. Required OUT =
          standard end + exact late minutes, on the same day. The first two late
          arrivals can be cleared by covering. Uncovered or additional late
          arrivals convert to short leave; after two short leaves, qualifying
          instances become half-days. Other Staff short-leave boundaries:
          arrival by 10:00 AM / departure from 2:45 PM. PL short-leave
          conversions remain for review until their windows are defined.
        </p>
        <p>
          <strong>LC — Late Come · SL — Short Leave · HD — Half Day.</strong>{" "}
          Evaluation runs immediately after import. Balance deductions follow
          the administrator’s automatic-deduction setting.
        </p>
      </section>
    </>
  );
}
