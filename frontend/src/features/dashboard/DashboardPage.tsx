import { useQuery } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import {
  Users,
  Building2,
  Upload,
  ArrowUpRight,
  CalendarDays,
  ArrowRight,
} from "lucide-react";
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
  recent_attendance: {
    id: number;
    name: string;
    date: string;
    actual_in: string | null;
    actual_out: string | null;
    status: string;
  }[];
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
                note: "All employee records",
              },
              {
                label: "Divisions",
                value: d?.total_divisions,
                icon: Building2,
                path: "/divisions",
                note: "Organizational divisions",
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

          {/* Upload Attendance File CTA */}
          <Link to="/attendance/upload" className="upload-cta-banner">
            <div className="upload-cta-icon">
              <Upload size={36} />
            </div>
            <div className="upload-cta-text">
              <strong>Upload Attendance File</strong>
              <span>Import TXT, DAT or CSV fingerprint export files to process daily attendance records</span>
            </div>
            <div className="upload-cta-action">
              <span>Upload now</span>
              <ArrowRight size={18} />
            </div>
          </Link>
        </>
      )}
    </>
  );
}

