import { useEffect, useRef, useId, isValidElement, cloneElement } from "react";
import type { ReactNode } from "react";
import {
  AlertCircle,
  Inbox,
  Loader2,
  X,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { errorMessage } from "../lib/api";
export function State({
  title,
  description,
  loading,
  error,
  retry,
}: {
  title: string;
  description?: string;
  loading?: boolean;
  error?: unknown;
  retry?: () => void;
}) {
  return (
    <div className="state" role={error ? "alert" : "status"}>
      {loading ? (
        <Loader2 className="spin" size={28} />
      ) : error ? (
        <AlertCircle size={28} />
      ) : (
        <Inbox size={30} />
      )}
      <h3>{title}</h3>
      <p>{error ? errorMessage(error) : description}</p>
      {retry && (
        <button className="button secondary" onClick={retry}>
          Try again
        </button>
      )}
    </div>
  );
}
export function Modal({
  title,
  children,
  onClose,
}: {
  title: string;
  children: ReactNode;
  onClose: () => void;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const el = ref.current;
    el?.showModal();
    return () => el?.close();
  }, []);
  return (
    <dialog ref={ref} className="modal" aria-label={title} onCancel={onClose}>
      <div className="modal-head">
        <h2>{title}</h2>
        <button
          className="icon-button"
          aria-label="Close dialog"
          onClick={onClose}
        >
          <X size={20} />
        </button>
      </div>
      {children}
    </dialog>
  );
}
export function Confirm({
  title,
  description,
  busy,
  onConfirm,
  onClose,
}: {
  title: string;
  description: string;
  busy: boolean;
  onConfirm: () => void;
  onClose: () => void;
}) {
  return (
    <Modal title={title} onClose={onClose}>
      <p className="modal-description">{description}</p>
      <div className="modal-actions">
        <button autoFocus className="button secondary" onClick={onClose}>
          Cancel
        </button>
        <button className="button danger" disabled={busy} onClick={onConfirm}>
          {busy ? "Processing…" : "Confirm"}
        </button>
      </div>
    </Modal>
  );
}
export function Pagination({
  page,
  last,
  total,
  onChange,
}: {
  page: number;
  last: number;
  total: number;
  onChange: (page: number) => void;
}) {
  return (
    <div className="pagination">
      <span>
        {total.toLocaleString()} record{total === 1 ? "" : "s"} · Page {page} of{" "}
        {Math.max(last, 1)}
      </span>
      <div>
        <button
          className="icon-button"
          disabled={page <= 1}
          aria-label="Previous page"
          onClick={() => onChange(page - 1)}
        >
          <ChevronLeft size={18} />
        </button>
        <button
          className="icon-button"
          disabled={page >= last}
          aria-label="Next page"
          onClick={() => onChange(page + 1)}
        >
          <ChevronRight size={18} />
        </button>
      </div>
    </div>
  );
}
export function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: ReactNode;
}) {
  const labelId = useId();
  const errorId = `${labelId}-error`;
  const control =
    isValidElement<Record<string, unknown>>(children) &&
    typeof children.type === "string"
      ? cloneElement(children, {
          "aria-labelledby": labelId,
          "aria-describedby": error
            ? errorId
            : children.props["aria-describedby"],
          "aria-invalid": error ? true : children.props["aria-invalid"],
        })
      : children;
  return (
    <label className="field">
      <span id={labelId}>{label}</span>
      {control}
      {error && (
        <span className="field-error" role="alert" id={errorId}>
          {error}
        </span>
      )}
    </label>
  );
}
