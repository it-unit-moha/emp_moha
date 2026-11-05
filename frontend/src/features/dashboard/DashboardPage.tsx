import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import {
  Users,
  Building2,
  Network,
  ArrowUpRight,
  CalendarCheck,
  Clock3,
  UserMinus,
  CalendarDays,
  ArrowRight,
} from "lucide-react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { api, endpoint } from "../../lib/api";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
interface Dashboard {
  total_employees: number;
  total_divisions: number;
  total_units: number;
  present: number | null;
  absent: number | null;
  late: number | null;
  on_leave: number | null;
  employees_by_division: { name: string; employees: number }[];
  recent_attendance: unknown[];
  attendance_status: string;
}
export function DashboardPage() {
  const { user } = useAuth();
  const query = useQuery({
    queryKey: ["dashboard"],
    queryFn: async () =>
      (await api.get<{ data: Dashboard }>(endpoint("dashboard"))).data.data,
  });
  const d = query.data;
  return (
    <>
      <PageHeading
        eyebrow="ADMINISTRATION OVERVIEW"
        title="Dashboard"
        description={`Welcome back, ${user?.name.split(" ")[0]}. Here is your division at a glance.`}
        action={
          <span className="date-pill">
            <CalendarDays size={16} />
            {new Intl.DateTimeFormat("en-GB", {
              dateStyle: "medium",
              timeZone: "Asia/Colombo",
            }).format(new Date())}
          </span>
        }
      />
      {query.isError ? (
        <State
          title="Dashboard could not load"
          error={query.error}
          retry={() => void query.refetch()}
        />
      ) : (
        <>
          <div className="stats-grid">
            {[
              {
                label: "Total employees",
                value: d?.total_employees,
                icon: Users,
                path: "/employees",
                note: "All imported employee records",
              },
              {
                label: "Divisions",
                value: d?.total_divisions,
                icon: Building2,
                path: "/divisions",
                note: "Organizational divisions",
              },
              {
                label: "Units",
                value: d?.total_units,
                icon: Network,
                path: "/units",
                note: "Registered administrative units",
              },
            ].map(({ label, value, icon: Icon, path, note }) => (
              <Link className="stat-card" to={path} key={label}>
                <div className="stat-top">
                  <span>{label}</span>
                  <span className="stat-icon">
                    <Icon size={21} />
                  </span>
                </div>
                <strong>
                  {value === undefined ? (
                    <span className="skeleton">&nbsp;</span>
                  ) : (
                    value.toLocaleString()
                  )}
                </strong>
                <div className="stat-note">
                  {note}
                  <ArrowUpRight size={15} />
                </div>
              </Link>
            ))}
          </div>
          <section className="panel attendance-overview">
            <div className="panel-header">
              <div>
                <h2>Today's attendance</h2>
                <p>Daily workforce availability</p>
              </div>
              <span className="status neutral">Awaiting attendance source</span>
            </div>
            <div className="attendance-stats">
              {[
                { label: "Present", value: d?.present, icon: CalendarCheck },
                { label: "Absent", value: d?.absent, icon: UserMinus },
                { label: "Late arrivals", value: d?.late, icon: Clock3 },
                { label: "On leave", value: d?.on_leave, icon: CalendarDays },
              ].map(({ label, value, icon: Icon }) => (
                <div key={label}>
                  <Icon size={19} />
                  <strong>{value == null ? "—" : value}</strong>
                  <span>{label}</span>
                </div>
              ))}
            </div>
            <div className="panel-note">
              Attendance figures will appear after a verified attendance source
              is connected.
            </div>
          </section>
          <div className="dashboard-columns">
            <section className="panel">
              <div className="panel-header">
                <div>
                  <h2>Employees by division</h2>
                  <p>Distribution across the organization</p>
                </div>
                <span className="small-badge">Live records</span>
              </div>
              {query.isPending ? (
                <State title="Loading division data" loading />
              ) : d?.employees_by_division.length ? (
                <div
                  className="chart"
                  role="img"
                  aria-label="Bar chart of employee counts by division"
                >
                  <ResponsiveContainer width="100%" height={280}>
                    <BarChart
                      data={d.employees_by_division}
                      margin={{ left: 0, right: 18, bottom: 10 }}
                    >
                      <XAxis
                        dataKey="name"
                        tick={{ fontSize: 10 }}
                        interval={0}
                        tickFormatter={(v) => String(v).slice(0, 12)}
                      />
                      <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                      <Tooltip />
                      <Bar
                        dataKey="employees"
                        name="Employees"
                        fill="#2f6495"
                        radius={[4, 4, 0, 0]}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                  <details className="chart-data">
                    <summary>View accessible data table</summary>
                    <table>
                      <thead>
                        <tr>
                          <th>Division</th>
                          <th>Employees</th>
                        </tr>
                      </thead>
                      <tbody>
                        {d.employees_by_division.map((r, i) => (
                          <tr key={i}>
                            <td>{r.name}</td>
                            <td>{r.employees}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </details>
                </div>
              ) : (
                <State
                  title="No division data"
                  description="Add divisions and assign employees to see their distribution."
                />
              )}
            </section>
            <section className="panel">
              <div className="panel-header">
                <div>
                  <h2>Quick access</h2>
                  <p>Your everyday administration tools</p>
                </div>
              </div>
              <div className="quick-links">
                {[
                  {
                    to: "/employees",
                    title: "Employee directory",
                    text: "Manage records and assignments",
                    icon: Users,
                  },
                  {
                    to: "/attendance",
                    title: "Attendance workspace",
                    text: "Schedules and import readiness",
                    icon: CalendarCheck,
                  },
                  {
                    to: "/leaves",
                    title: "Leave administration",
                    text: "Leave policies and readiness",
                    icon: CalendarDays,
                  },
                ].map(({ to, title, text, icon: Icon }) => (
                  <Link to={to} key={to}>
                    <span className="quick-icon">
                      <Icon size={20} />
                    </span>
                    <div>
                      <strong>{title}</strong>
                      <small>{text}</small>
                    </div>
                    <ArrowRight size={17} />
                  </Link>
                ))}
              </div>
              <div className="help-note">
                <ShieldNote />
              </div>
            </section>
          </div>
          <section className="panel">
            <div className="panel-header">
              <div>
                <h2>Recent attendance</h2>
                <p>The latest recorded attendance activity</p>
              </div>
            </div>
            <State
              title="No attendance records available"
              description="Records will appear here once the attendance integration is ready."
            />
          </section>
        </>
      )}
    </>
  );
}
function ShieldNote() {
  return (
    <>
      <strong>A reliable record, every day</strong>
      <p>
        Review employee assignments to keep your organizational records
        accurate.
      </p>
    </>
  );
}
