import { CalendarDays, ShieldCheck } from "lucide-react";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useQuery } from "@tanstack/react-query";
import { api, endpoint } from "../../lib/api";
interface LeaveResponse {
  data: unknown[];
  meta: {
    message: string;
    implemented: boolean;
    rules: Record<string, number | boolean | number[]>;
  };
}
export function LeavePage() {
  const query = useQuery({
    queryKey: ["leaves"],
    queryFn: async () =>
      (await api.get<LeaveResponse>(endpoint("leaves"))).data,
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
