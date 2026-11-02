import { useQuery } from "@tanstack/react-query";
import {
  CalendarClock,
  FileUp,
  ShieldCheck,
  Clock3,
  CalendarDays,
  FileBarChart2,
  Users,
  Settings,
} from "lucide-react";
import { api, endpoint } from "../../lib/api";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
interface Foundation {
  data: unknown[];
  meta: {
    message: string;
    implemented: boolean;
    rules?: Record<string, number | boolean | number[]>;
    schedules?: Record<string, { start: string; end: string }>;
  };
}
export function AttendancePage() {
  const query = useQuery({
    queryKey: ["attendance"],
    queryFn: async () =>
      (await api.get<Foundation>(endpoint("attendance"))).data,
  });
  return (
    <>
      <PageHeading
        eyebrow="DAILY OPERATIONS"
        title="Attendance Management"
        description="Attendance records, schedules and device integration."
      />
      <div className="info-banner">
        <ShieldCheck size={20} />
        <div>
          <strong>Device integration pending verification</strong>
          <p>
            A real device export is needed before importing attendance. No
            biometric columns or punch formats are assumed.
          </p>
        </div>
      </div>
      <div className="dashboard-columns">
        <section className="panel">
          <div className="panel-header">
            <div>
              <h2>Work schedules</h2>
              <p>Configured attendance windows</p>
            </div>
            <CalendarClock size={21} />
          </div>
          {query.isPending ? (
            <State title="Loading schedules" loading />
          ) : query.isError ? (
            <State
              title="Schedules unavailable"
              error={query.error}
              retry={() => void query.refetch()}
            />
          ) : (
            <div className="schedule-list">
              {Object.entries(query.data?.meta.schedules ?? {}).map(
                ([key, value]) => (
                  <div key={key}>
                    <span className="quick-icon">
                      <Clock3 size={20} />
                    </span>
                    <div>
                      <strong>
                        {key === "pl" ? "PL Staff" : "Other Staff"}
                      </strong>
                      <small>Standard work schedule</small>
                    </div>
                    <span>
                      {value.start} – {value.end}
                    </span>
                  </div>
                ),
              )}
            </div>
          )}
        </section>
        <section className="panel">
          <div className="panel-header">
            <div>
              <h2>Attendance import</h2>
              <p>CSV · TXT · DAT</p>
            </div>
            <FileUp size={21} />
          </div>
          <State
            title="Import is not enabled"
            description="Parser verification, duplicate detection, import history and transactional storage must be completed before uploads are accepted."
          />
        </section>
      </div>
      <section className="panel">
        <div className="panel-header">
          <div>
            <h2>Attendance log</h2>
            <p>Daily summaries, late arrivals and missing punches</p>
          </div>
          <span className="status neutral">Integration pending</span>
        </div>
        <State
          title="No attendance records available"
          description="Connect a verified device source to begin attendance review."
        />
      </section>
    </>
  );
}
export function LeavePage() {
  const query = useQuery({
    queryKey: ["leaves"],
    queryFn: async () => (await api.get<Foundation>(endpoint("leaves"))).data,
  });
  const r = query.data?.meta.rules;
  return (
    <>
      <PageHeading
        eyebrow="EMPLOYEE WELLBEING"
        title="Leave Management"
        description="Leave policies, balances, applications and approval readiness."
      />
      <div className="info-banner">
        <ShieldCheck size={20} />
        <div>
          <strong>Policy validation in progress</strong>
          <p>
            Eligibility, holidays and approval rules require stakeholder
            sign-off. Automatic late-day deductions are disabled.
          </p>
        </div>
      </div>
      {query.isPending ? (
        <State title="Loading policy configuration" loading />
      ) : query.isError ? (
        <State
          title="Leave configuration unavailable"
          error={query.error}
          retry={() => void query.refetch()}
        />
      ) : (
        <div className="policy-grid">
          {[
            {
              title: "Casual leave",
              value: `${r?.casual_days ?? "—"} days`,
              note: "Annual allowance; eligibility to be verified",
            },
            {
              title: "Vacation leave",
              value: `${Array.isArray(r?.vacation_days) ? r.vacation_days.join(" / ") : "—"} days`,
              note: "Allowance depends on eligibility",
            },
            {
              title: "Maternity leave",
              value: `${r?.maternity_working_days ?? "—"} working days`,
              note: "Calendar and eligibility rules pending",
            },
            {
              title: "Paternity leave",
              value: `${r?.paternity_days ?? "—"} days`,
              note: "Supporting approval rules pending",
            },
            {
              title: "Short leave",
              value: `${r?.short_leave_minutes ?? "—"} minutes`,
              note: `Maximum ${r?.short_leave_monthly_limit ?? "—"} instances per month`,
            },
            {
              title: "Late-day review",
              value: "Manual review",
              note: `${r?.late_days_per_half_day ?? "—"} uncovered late days may trigger half-day deduction`,
            },
          ].map((p) => (
            <section className="panel policy-card" key={p.title}>
              <CalendarDays size={20} />
              <h2>{p.title}</h2>
              <strong>{p.value}</strong>
              <p>{p.note}</p>
              <span className="status neutral">
                Configuration · awaiting approval
              </span>
            </section>
          ))}
        </div>
      )}
      <section className="panel">
        <div className="panel-header">
          <div>
            <h2>Leave applications</h2>
            <p>Applications, approvals, balances and deduction history</p>
          </div>
        </div>
        <State
          title="Leave workflow is not enabled"
          description="Applications and balances will become available after the approved rules and leave schema are implemented."
        />
      </section>
    </>
  );
}
export function ReportsPage() {
  return (
    <>
      <PageHeading
        eyebrow="INSIGHTS"
        title="Reports"
        description="Attendance and leave reporting workspace."
      />
      <section className="panel">
        <div className="panel-header">
          <h2>Reporting readiness</h2>
          <FileBarChart2 size={22} />
        </div>
        <State
          title="Reports are awaiting verified source data"
          description="Attendance summaries, leave balances and export reports will be enabled after their source modules are validated."
        />
      </section>
    </>
  );
}
export function UsersPage() {
  const { user } = useAuth();
  const allowed = user?.role === "Super Admin" || user?.role === "Admin";
  return (
    <>
      <PageHeading
        eyebrow="ACCESS & SECURITY"
        title="User Management"
        description="Administrative accounts and role-based access."
      />
      <section className="panel">
        <div className="panel-header">
          <h2>Access administration</h2>
          <Users size={22} />
        </div>
        <State
          title={
            allowed
              ? "Account administration is not enabled in the portal"
              : "Administrator access required"
          }
          description={
            allowed
              ? "Provision an initial administrator with the documented secure artisan command. Account CRUD, resets and role changes require a dedicated audited workflow."
              : "Only Super Admin and Admin roles can manage user accounts."
          }
        />
      </section>
    </>
  );
}
export function SettingsPage() {
  const { user } = useAuth();
  return (
    <>
      <PageHeading
        eyebrow="WORKSPACE PREFERENCES"
        title="Settings"
        description="Your account and workspace configuration."
      />
      <div className="dashboard-columns">
        <section className="panel">
          <div className="panel-header">
            <h2>Signed-in account</h2>
            <Settings size={21} />
          </div>
          <dl className="detail-grid settings-details">
            <div>
              <dt>Name</dt>
              <dd>{user?.name}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{user?.email}</dd>
            </div>
            <div>
              <dt>Role</dt>
              <dd>{user?.role}</dd>
            </div>
          </dl>
        </section>
        <section className="panel">
          <div className="panel-header">
            <h2>Business configuration</h2>
          </div>
          <State
            title="Managed by the system administrator"
            description="Schedules and draft leave policies are maintained in backend configuration. Policy editing requires a reviewed approval workflow."
          />
        </section>
      </div>
    </>
  );
}
