import { useEffect, useRef, useState } from "react";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Users,
  CalendarCheck,
  CalendarDays,
  Building2,
  Network,
  BriefcaseBusiness,
  ChartNoAxesCombined,
  ShieldCheck,
  Settings,
  Menu,
  X,
  ChevronDown,
  LogOut,
  Landmark,
  ChevronRight,
} from "lucide-react";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { api, endpoint, errorMessage } from "../lib/api";
import { useAuth } from "../features/auth/Auth";
export const navigation = [
  { path: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { path: "employees", label: "Employee Management", icon: Users },
  { path: "attendance", label: "Attendance Management", icon: CalendarCheck },
  { path: "leaves", label: "Leave Management", icon: CalendarDays },
  { path: "divisions", label: "Divisions", icon: Building2 },
  { path: "units", label: "Units", icon: Network },
  { path: "designations", label: "Designations", icon: BriefcaseBusiness },
  { path: "reports", label: "Reports", icon: ChartNoAxesCombined },
  { path: "users", label: "User Management", icon: ShieldCheck },
  { path: "settings", label: "Settings", icon: Settings },
];
export function Layout() {
  const [open, setOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const sidebarRef = useRef<HTMLElement>(null);
  const menuRef = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    const media = window.matchMedia("(max-width: 800px)");
    const synchronize = () =>
      sidebarRef.current?.toggleAttribute("inert", media.matches && !open);
    synchronize();
    media.addEventListener("change", synchronize);
    if (open && media.matches)
      sidebarRef.current
        ?.querySelector<HTMLButtonElement>(".mobile-close")
        ?.focus();
    const escape = (event: KeyboardEvent) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        menuRef.current?.focus();
      }
    };
    document.addEventListener("keydown", escape);
    return () => {
      media.removeEventListener("change", synchronize);
      document.removeEventListener("keydown", escape);
    };
  }, [open]);
  const { user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const client = useQueryClient();
  const active = navigation.find((n) =>
    location.pathname.startsWith("/" + n.path),
  );
  async function logout() {
    setLoggingOut(true);
    try {
      await api.post(endpoint("logout"));
      client.clear();
      navigate("/login", { replace: true });
      window.location.reload();
    } catch (e) {
      toast.error(errorMessage(e));
    } finally {
      setLoggingOut(false);
    }
  }
  return (
    <div className="app-shell">
      <a className="skip-link" href="#main-content">
        Skip to main content
      </a>
      {open && (
        <button
          className="sidebar-overlay"
          onClick={() => setOpen(false)}
          aria-label="Close navigation"
        />
      )}
      <aside
        ref={sidebarRef}
        className={`sidebar ${open ? "open" : ""}`}
        aria-label="Main navigation"
      >
        <div className="brand">
          <span className="brand-icon">
            <Landmark size={27} />
          </span>
          <div>
            <strong>GALMS</strong>
            <small>HOME AFFAIRS DIVISION</small>
          </div>
          <button
            className="mobile-close icon-button"
            aria-label="Close navigation"
            onClick={() => setOpen(false)}
          >
            <X />
          </button>
        </div>
        <div className="sidebar-caption">WORKSPACE</div>
        <nav>
          {navigation.map(({ path, label, icon: Icon }, i) => (
            <div key={path}>
              {i === 4 && (
                <div className="sidebar-caption section-caption">
                  ADMINISTRATION
                </div>
              )}
              {i === 8 && <div className="nav-divider" />}
              <NavLink
                to={"/" + path}
                onClick={() => setOpen(false)}
                className={({ isActive }) =>
                  `nav-item ${isActive ? "active" : ""}`
                }
              >
                <Icon size={19} />
                <span>{label}</span>
              </NavLink>
            </div>
          ))}
        </nav>
        <div className="sidebar-footer">
          <ShieldCheck size={18} />
          <div>
            Official administration portal
            <small>Home Affairs Division · GALMS</small>
          </div>
        </div>
      </aside>
      <div className="workspace">
        <header className="topbar">
          <div className="topbar-left">
            <button
              ref={menuRef}
              className="icon-button mobile-menu"
              aria-label="Open navigation"
              aria-expanded={open}
              onClick={() => setOpen(true)}
            >
              <Menu />
            </button>
            <span>Attendance & Leave Management</span>
            <span className="environment-badge">Internal portal</span>
          </div>
          <details className="profile">
            <summary>
              <span className="avatar">
                {user?.name?.slice(0, 1).toUpperCase()}
              </span>
              <span className="profile-copy">
                <strong>{user?.name}</strong>
                <small>{user?.role}</small>
              </span>
              <ChevronDown size={16} />
            </summary>
            <div className="profile-popover">
              <p>{user?.email}</p>
              <button disabled={loggingOut} onClick={() => void logout()}>
                <LogOut size={16} />
                {loggingOut ? "Signing out…" : "Sign out"}
              </button>
            </div>
          </details>
        </header>
        <main id="main-content" tabIndex={-1}>
          <div className="breadcrumbs">
            <span>Workspace</span>
            <ChevronRight size={13} />
            <span>{active?.label}</span>
          </div>
          <Outlet />
        </main>
        <footer className="workspace-footer">
          <span>GALMS · Home Affairs Division</span>
          <span>Attendance & Leave Management System</span>
        </footer>
      </div>
    </div>
  );
}
export function PageHeading({
  eyebrow,
  title,
  description,
  action,
}: {
  eyebrow?: string;
  title: string;
  description: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="page-heading">
      <div>
        {eyebrow && <div className="eyebrow">{eyebrow}</div>}
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {action}
    </div>
  );
}
