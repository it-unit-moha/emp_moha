import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import {
  Landmark,
  ArrowRight,
  ShieldCheck,
  Mail,
  LockKeyhole,
  Eye,
  EyeOff,
  Loader2,
} from "lucide-react";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { useAuth } from "./Auth";
import { AnimatedFeatureCards } from "./AnimatedFeatureCards";
import { ArchitecturalBackdrop } from "./ArchitecturalBackdrop";
import { useLoginMotion } from "./useLoginMotion";
import "./login.css";
const schema = z.object({
  email: z.string().email("Enter a valid email address."),
  password: z.string().min(1, "Enter your password."),
});
type Values = z.infer<typeof schema>;
export function LoginPage() {
  const { user, reload } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [error, setError] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const {
    register,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema) });
  const scope = useLoginMotion(!!user, isSubmitting);
  if (user) return <Navigate to="/dashboard" replace />;
  async function submit(data: Values) {
    setError("");
    try {
      await api.get("/sanctum/csrf-cookie");
      await api.post(endpoint("login"), data);
      await reload();
      const from = location.state?.from;
      navigate(
        typeof from === "string" &&
          from.startsWith("/") &&
          !from.startsWith("//")
          ? from
          : "/dashboard",
        { replace: true },
      );
    } catch (e) {
      setError(errorMessage(e));
      Object.entries(fieldErrors(e)).forEach(([key, value]) =>
        setFieldError(key as keyof Values, { message: value[0] }),
      );
    }
  }
  return (
    <div className="galms-login" ref={scope}>
      <div className="galms-ambient" aria-hidden="true" />
      <section className="galms-intro" aria-labelledby="galms-headline">
        <div className="galms-spotlight" aria-hidden="true" />
        <div className="galms-grid" aria-hidden="true" />
        <ArchitecturalBackdrop />
        <div className="galms-aura" aria-hidden="true">
          <i />
          <i />
          <i />
        </div>
        <header className="galms-brand">
          <Landmark size={45} strokeWidth={1.6} aria-hidden="true" />
          <strong>GALMS</strong>
          <span>
            GOVERNMENT ATTENDANCE &amp;
            <br />
            LEAVE MANAGEMENT SYSTEM
          </span>
        </header>
        <div className="galms-intro-content">
          <p className="galms-division">
            <i aria-hidden="true" />
            HOME AFFAIRS DIVISION
          </p>
          <h1 className="galms-headline" id="galms-headline">
            <span className="galms-headline-mask">
              <span>Better administration.</span>
            </span>
            <span className="galms-headline-mask">
              <span>Every working day.</span>
            </span>
          </h1>
          <p className="galms-description">
            A connected workspace for employee records,
            <br className="galms-desktop-break" /> attendance and leave
            administration.
          </p>
          <AnimatedFeatureCards />
        </div>
        <footer className="galms-footer">
          <span>
            <ShieldCheck size={23} aria-hidden="true" />
            Official access for authorized personnel
          </span>
          <span>
            <Landmark size={22} aria-hidden="true" />
            Ministry of Home Affairs
          </span>
        </footer>
      </section>
      <main className="galms-panel" aria-labelledby="galms-signin-title">
        <div className="galms-form-content">
          <p className="galms-welcome galms-form-reveal">
            WELCOME TO YOUR WORKSPACE
          </p>
          <h2 className="galms-form-reveal" id="galms-signin-title">
            Sign in to <span>GALMS</span>
          </h2>
          <p className="galms-form-description galms-form-reveal">
            Use the account provided by your administrator.
          </p>
          <form
            onSubmit={handleSubmit(submit)}
            noValidate
            aria-busy={isSubmitting}
          >
            <div className="galms-field galms-form-reveal">
              <label htmlFor="galms-email">
                <Mail size={20} aria-hidden="true" />
                Email address
              </label>
              <input
                id="galms-email"
                type="email"
                autoComplete="username"
                placeholder="Enter your official email"
                {...register("email")}
                aria-invalid={!!errors.email}
                aria-describedby={
                  errors.email ? "galms-email-error" : undefined
                }
              />
              {errors.email && (
                <p
                  className="galms-field-error"
                  id="galms-email-error"
                  role="alert"
                >
                  {errors.email.message}
                </p>
              )}
            </div>
            <div className="galms-field galms-form-reveal">
              <label htmlFor="galms-password">
                <LockKeyhole size={20} aria-hidden="true" />
                Password
              </label>
              <div className="galms-password-wrap">
                <input
                  id="galms-password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  placeholder="Enter your password"
                  {...register("password")}
                  aria-invalid={!!errors.password}
                  aria-describedby={
                    errors.password ? "galms-password-error" : undefined
                  }
                />
                <button
                  className="galms-password-toggle"
                  type="button"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  aria-pressed={showPassword}
                  aria-controls="galms-password"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                </button>
              </div>
              {errors.password && (
                <p
                  className="galms-field-error"
                  id="galms-password-error"
                  role="alert"
                >
                  {errors.password.message}
                </p>
              )}
            </div>
            <div className="galms-feedback" aria-live="polite">
              {error && (
                <p role="alert" className="error-banner">
                  {error}
                </p>
              )}
            </div>
            <button
              className="galms-submit galms-form-reveal"
              type="submit"
              disabled={isSubmitting}
            >
              <span>{isSubmitting ? "Signing in?" : "Sign in"}</span>
              {isSubmitting ? (
                <Loader2
                  className="galms-loading-icon"
                  size={21}
                  aria-hidden="true"
                />
              ) : (
                <ArrowRight size={23} aria-hidden="true" />
              )}
            </button>
          </form>
          <p className="galms-help galms-form-reveal">
            Need access? Contact your system administrator.
          </p>
          <div
            className="galms-panel-seal galms-form-reveal"
            aria-hidden="true"
          >
            <ShieldCheck size={15} /> YOUR WORKSPACE. SECURELY CONNECTED.
          </div>
        </div>
      </main>
      <svg
        className="galms-waves"
        viewBox="0 0 1600 220"
        preserveAspectRatio="none"
        aria-hidden="true"
      >
        <defs>
          <linearGradient id="galms-wave-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop stopColor="#a8ff91" />
            <stop offset=".4" stopColor="#08c9a2" />
            <stop offset="1" stopColor="#0c8174" stopOpacity="0" />
          </linearGradient>
        </defs>
        <path
          d="M0 65C210 35 360 250 690 135S1130 15 1600 130V220H0Z"
          fill="url(#galms-wave-gradient)"
          opacity=".65"
        />
        <path
          d="M0 160C270 -35 420 225 740 155S1190 65 1600 145V220H0Z"
          fill="#032e32"
          opacity=".8"
        />
        <path
          d="M0 65C210 35 360 250 690 135S1130 15 1600 130"
          stroke="#a1ffd0"
          strokeWidth="2"
          fill="none"
          opacity=".5"
        />
      </svg>
    </div>
  );
}
