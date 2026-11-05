import { useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { Navigate, useLocation, useNavigate } from "react-router-dom";
import { Landmark, ArrowRight, ShieldCheck } from "lucide-react";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { useAuth } from "./Auth";
import { Field } from "../../components/ui";
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
  const {
    register,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema) });
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
    <div className="login-page">
      <section className="login-intro">
        <div className="login-brand">
          <Landmark size={36} />
          <strong>GALMS</strong>
        </div>
        <div>
          <span className="eyebrow">HOME AFFAIRS DIVISION</span>
          <h1>
            Better administration.
            <br />
            Every working day.
          </h1>
          <p>
            A connected workspace for employee records, attendance and leave
            administration.
          </p>
          <div className="login-security">
            <ShieldCheck />
            <span>Official access for authorized personnel</span>
          </div>
        </div>
        <small>Government Attendance & Leave Management System</small>
      </section>
      <main className="login-main">
        <div className="login-card">
          <span className="eyebrow">WELCOME TO YOUR WORKSPACE</span>
          <h2>Sign in to GALMS</h2>
          <p>Use the account provided by your administrator.</p>
          <form onSubmit={handleSubmit(submit)} noValidate>
            <Field label="Email address" error={errors.email?.message}>
              <input
                type="email"
                autoComplete="username"
                {...register("email")}
                aria-invalid={!!errors.email}
              />
            </Field>
            <Field label="Password" error={errors.password?.message}>
              <input
                type="password"
                autoComplete="current-password"
                {...register("password")}
                aria-invalid={!!errors.password}
              />
            </Field>
            {error && (
              <p role="alert" className="error-banner">
                {error}
              </p>
            )}
            <button
              className="button primary login-button"
              disabled={isSubmitting}
            >
              {isSubmitting ? "Signing in…" : "Sign in"}
              <ArrowRight size={17} />
            </button>
          </form>
          <p className="login-help">
            Need access? Contact your system administrator.
          </p>
        </div>
      </main>
    </div>
  );
}
