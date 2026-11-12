import { NavLink } from "react-router-dom";
import { PageHeading } from "../../components/Layout";
import { State } from "../../components/ui";
import { useAuth } from "../auth/Auth";
import { MultiMachineUpload } from "./MultiMachineUpload";
import "./attendance.css";

export function AttendanceTabs() {
  const { user } = useAuth();
  return (
    <nav className="operations-toolbar" aria-label="Attendance sections">
      <NavLink
        to="/attendance"
        end
        className={({ isActive }) =>
          `button ${isActive ? "primary" : "secondary"}`
        }
      >
        Attendance history
      </NavLink>
      {user && user.role !== "Authorized Viewer" && (
        <NavLink
          to="/attendance/upload"
          className={({ isActive }) =>
            `button ${isActive ? "primary" : "secondary"}`
          }
        >
          Upload attendance
        </NavLink>
      )}
    </nav>
  );
}

export function AttendanceUploadPage() {
  const { user } = useAuth();
  return (
    <>
      <PageHeading
        eyebrow="ATTENDANCE WORKSPACE"
        title="Upload attendance"
        description="Import attendance files directly using employee enrollment IDs."
      />
      <AttendanceTabs />
      {user && user.role !== "Authorized Viewer" ? (
        <MultiMachineUpload />
      ) : (
        <State title="Attendance uploads require an authorized editor" />
      )}
    </>
  );
}
