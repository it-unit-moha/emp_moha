import { Users } from "lucide-react";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
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
