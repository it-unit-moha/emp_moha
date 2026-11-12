import { useEffect, useRef, useState } from "react";
import { useWorkspaceMotion } from "./useWorkspaceMotion";
import "./workspace.css";
import { NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  ClipboardList,
  ScrollText,
  Users,
  CalendarCheck,
  CalendarDays,
  Building2,
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
  {
    path: "dashboard",
    label: "Dashboard",
    icon: LayoutDashboard,
    group: "WORKSPACE",
  },
  {
    path: "employees",
    label: "Employee register",
    icon: Users,
    group: "WORKSPACE",
  },
  {
    path: "divisions",
    label: "Browse divisions",
    icon: Building2,
    group: "WORKSPACE",
  },
  {
    path: "attendance",
    label: "Attendance",
    icon: CalendarCheck,
    group: "WORKSPACE",
  },
  {
    path: "leaves",
    label: "Leave balances",
    icon: CalendarDays,
    group: "WORKSPACE",
  },
  {
    path: "setup",
    label: "Getting started",
    icon: ClipboardList,
    group: "ORGANIZATION SETUP",
  },
  {
    path: "divisions/manage",
    label: "Manage divisions",
    icon: Building2,
    group: "ORGANIZATION SETUP",
  },
  {
    path: "users",
    label: "User management",
    icon: ShieldCheck,
    group: "ADMINISTRATION",
  },
  {
    path: "audit",
    label: "Audit history",
    icon: ScrollText,
    group: "ADMINISTRATION",
  },
  {
    path: "settings",
    label: "Settings",
    icon: Settings,
    group: "ADMINISTRATION",
  },
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
  const motionRef = useWorkspaceMotion(location.pathname);
  const navigate = useNavigate();
  const client = useQueryClient();
  const active = [...navigation]
    .sort((a, b) => b.path.length - a.path.length)
    .find((n) => location.pathname.startsWith("/" + n.path));
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
    <div className="app-shell" ref={motionRef}>
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

        <nav>
          {navigation.map(({ path, label, icon: Icon, group }, i) => (
            <div key={path}>
              {(i === 0 || navigation[i - 1].group !== group) && (
                <div className="sidebar-caption section-caption">{group}</div>
              )}

              <NavLink
                to={"/" + path}
                end={path === "divisions"}
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
