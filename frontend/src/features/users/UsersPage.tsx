import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { useAuth } from "../auth/Auth";
import { PageHeading } from "../../components/Layout";
import { Field, State, Modal } from "../../components/ui";
interface Account {
  id: number;
  name: string;
  email: string;
  role: string;
  is_active: boolean;
}
export function UsersPage() {
  const { user } = useAuth();
  const allowed = user?.role === "Super Admin" || user?.role === "Admin";
  const client = useQueryClient();
  const [editing, setEditing] = useState<Account | null | undefined>();
  const query = useQuery({
    queryKey: ["users"],
    enabled: allowed,
    queryFn: async () =>
      (await api.get<{ data: Account[] }>(endpoint("users"))).data.data,
  });
  const mutation = useMutation({
    mutationFn: async (body: Record<string, unknown>) =>
      editing
        ? api.put(endpoint(`users/${editing.id}`), body)
        : api.post(endpoint("users"), body),
    onSuccess: () => {
      toast.success("Account saved");
      setEditing(undefined);
      void client.invalidateQueries({ queryKey: ["users"] });
    },
    onError: (error) =>
      toast.error(
        Object.values(fieldErrors(error)).flat()[0] ?? errorMessage(error),
      ),
  });
  return (
    <>
      <PageHeading
        eyebrow="ACCESS & SECURITY"
        title="User Management"
        description="Create administrative accounts, assign roles and disable access without deleting audit history."
        action={
          allowed && (
            <button
              className="button primary"
              onClick={() => {
                mutation.reset();
                setEditing(null);
              }}
            >
              Add account
            </button>
          )
        }
      />
      {!allowed ? (
        <State
          title="Administrator access required"
          description="Only Admin and Super Admin accounts can manage access."
        />
      ) : query.isError ? (
        <State title="Accounts unavailable" error={query.error} />
      ) : (
        <section className="panel">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {query.data?.map((account) => (
                  <tr key={account.id}>
                    <td>{account.name}</td>
                    <td>{account.email}</td>
                    <td>{account.role}</td>
                    <td>
                      <span
                        className={`status ${account.is_active ? "active" : "neutral"}`}
                      >
                        {account.is_active ? "Active" : "Disabled"}
                      </span>
                    </td>
                    <td>
                      <button
                        className="button secondary"
                        onClick={() => {
                          mutation.reset();
                          setEditing(account);
                        }}
                      >
                        Edit account
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
      {editing !== undefined && (
        <Modal
          title={editing ? "Edit account" : "Add account"}
          onClose={() => setEditing(undefined)}
        >
          <form
            onSubmit={(event) => {
              event.preventDefault();
              const body = Object.fromEntries(
                new FormData(event.currentTarget),
              );
              mutation.mutate({
                ...body,
                is_active: body.is_active === "true",
              });
            }}
          >
            <div className="modal-body form-grid">
              <Field label="Full name">
                <input
                  name="name"
                  defaultValue={editing?.name}
                  required
                  maxLength={255}
                />
              </Field>
              <Field label="Email address">
                <input
                  name="email"
                  type="email"
                  defaultValue={editing?.email}
                  required
                  maxLength={255}
                />
              </Field>
              <Field label="Role">
                <select
                  name="role"
                  defaultValue={editing?.role ?? "Authorized Viewer"}
                >
                  {[
                    "Super Admin",
                    "Admin",
                    "HR Officer",
                    "Authorized Viewer",
                  ].map((role) => (
                    <option key={role}>{role}</option>
                  ))}
                </select>
              </Field>
              <Field label="Access status">
                <select
                  name="is_active"
                  defaultValue={editing?.is_active === false ? "false" : "true"}
                >
                  <option value="true">Active</option>
                  <option value="false">Disabled</option>
                </select>
              </Field>
              {!editing && (
                <Field label="Initial password (at least 12 characters)">
                  <input
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    minLength={12}
                    maxLength={128}
                    required
                  />
                </Field>
              )}
              {mutation.isError && (
                <p className="error-banner" role="alert">
                  {Object.values(fieldErrors(mutation.error))
                    .flat()
                    .join(" ") || errorMessage(mutation.error)}
                </p>
              )}
            </div>
            <div className="modal-actions">
              <button
                type="button"
                className="button secondary"
                onClick={() => setEditing(undefined)}
              >
                Cancel
              </button>
              <button className="button primary" disabled={mutation.isPending}>
                {mutation.isPending ? "Saving?" : "Save account"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
