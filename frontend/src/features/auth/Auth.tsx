import { createContext, useContext, useEffect } from "react";
import type { ReactNode } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { api, endpoint } from "../../lib/api";
import type { User } from "../../lib/api";
import { State } from "../../components/ui";
const AuthContext = createContext<{
  user: User | undefined;
  reload: () => Promise<unknown>;
}>({ user: undefined, reload: async () => {} });
export function AuthProvider({ children }: { children: ReactNode }) {
  const client = useQueryClient();
  const query = useQuery({
    queryKey: ["auth"],
    queryFn: async () =>
      (await api.get<{ data: User }>(endpoint("user"))).data.data,
    retry: false,
    staleTime: 60000,
  });
  useEffect(() => {
    const id = api.interceptors.response.use(
      (r) => r,
      (e) => {
        if (
          e.response?.status === 401 &&
          !e.config?.url?.endsWith("/user") &&
          !e.config?.url?.endsWith("/login")
        ) {
          client.setQueryData(["auth"], null);
          client.removeQueries({ predicate: (q) => q.queryKey[0] !== "auth" });
        }
        return Promise.reject(e);
      },
    );
    return () => api.interceptors.response.eject(id);
  }, [client]);
  return (
    <AuthContext.Provider
      value={{ user: query.data ?? undefined, reload: () => query.refetch() }}
    >
      {query.isPending ? (
        <main className="auth-loading">
          <State title="Connecting to GALMS" loading />
        </main>
      ) : query.isError &&
        (query.error as { response?: { status: number } }).response?.status !==
          401 ? (
        <main className="auth-loading">
          <State
            title="GALMS is unavailable"
            error={query.error}
            retry={() => void query.refetch()}
          />
        </main>
      ) : (
        children
      )}
    </AuthContext.Provider>
  );
}
export const useAuth = () => useContext(AuthContext);
export function ProtectedRoute() {
  const { user } = useAuth();
  const location = useLocation();
  return user ? (
    <Outlet />
  ) : (
    <Navigate to="/login" state={{ from: location.pathname }} replace />
  );
}
