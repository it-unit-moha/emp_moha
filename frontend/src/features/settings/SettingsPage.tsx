import { Settings } from "lucide-react";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
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
