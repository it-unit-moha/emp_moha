import { CalendarClock, FileUp, ShieldCheck, Clock3 } from "lucide-react";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useQuery } from "@tanstack/react-query";
import { api, endpoint } from "../../lib/api";
interface AttendanceResponse {
  data: unknown[];
  meta: {
    message: string;
    implemented: boolean;
    schedules: Record<string, { start: string; end: string }>;
  };
}
export function AttendancePage() {
  const query = useQuery({
    queryKey: ["attendance"],
    queryFn: async () =>
      (await api.get<AttendanceResponse>(endpoint("attendance"))).data,
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
