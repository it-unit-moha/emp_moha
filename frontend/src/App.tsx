import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom";
import { AuthProvider, ProtectedRoute } from "./features/auth/Auth";
import { LoginPage } from "./features/auth/LoginPage";
import { Layout } from "./components/Layout";
import { lazy, Suspense } from "react";
const DashboardPage = lazy(() =>
  import("./features/dashboard/DashboardPage").then((m) => ({
    default: m.DashboardPage,
  })),
);
import { EmployeePage } from "./features/employees/EmployeePage";
import { MasterDataPage } from "./features/master-data/MasterDataPage";
import { AttendancePage } from "./features/attendance/AttendancePage";
import { LeavePage } from "./features/leaves/LeavePage";
import { ReportsPage } from "./features/reports/ReportsPage";
import { UsersPage } from "./features/users/UsersPage";
import { SettingsPage } from "./features/settings/SettingsPage";
import { State } from "./components/ui";
export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route element={<ProtectedRoute />}>
            <Route element={<Layout />}>
              <Route index element={<Navigate to="/dashboard" replace />} />
              <Route
                path="dashboard"
                element={
                  <Suspense
                    fallback={<State title="Loading dashboard" loading />}
                  >
                    <DashboardPage />
                  </Suspense>
                }
              />
              <Route path="employees" element={<EmployeePage />} />
              <Route path="attendance" element={<AttendancePage />} />
              <Route path="leaves" element={<LeavePage />} />
              <Route
                path="divisions"
                element={
                  <MasterDataPage
                    key="divisions"
                    module="divisions"
                    title="Divisions"
                    description="Manage divisions within the Home Affairs organization."
                  />
                }
              />
              <Route
                path="units"
                element={
                  <MasterDataPage
                    key="units"
                    module="units"
                    title="Units"
                    description="Maintain the administrative unit register."
                  />
                }
              />
              <Route
                path="designations"
                element={
                  <MasterDataPage
                    key="designations"
                    module="designations"
                    title="Designations"
                    description="Maintain employee positions and official designations."
                  />
                }
              />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="users" element={<UsersPage />} />
              <Route path="settings" element={<SettingsPage />} />
              <Route
                path="*"
                element={
                  <State
                    title="Page not found"
                    description="Choose a module from the navigation to continue."
                  />
                }
              />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
