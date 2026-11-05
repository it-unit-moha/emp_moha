import { FileBarChart2 } from "lucide-react";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
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
