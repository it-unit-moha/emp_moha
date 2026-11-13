import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Upload, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { api, endpoint, errorMessage, fieldErrors } from "../../lib/api";
import { Field, State } from "../../components/ui";

interface ImportFile {
  id: number;
  filename: string;
  inserted: number;
  duplicates: number;
  repeated_file?: boolean;
  created_at?: string;
}
interface ImportResult {
  inserted: number;
  duplicates: number;
  evaluated_days: number;
  files: ImportFile[];
}
const detail = (e: unknown) =>
  Object.values(fieldErrors(e)).flat().join(" ") || errorMessage(e);

export function MultiMachineUpload() {
  const client = useQueryClient();
  const [queue, setQueue] = useState<File[]>([]);
  const [result, setResult] = useState<ImportResult | null>(null);
  const history = useQuery({
    queryKey: ["attendance-import-history"],
    queryFn: async () =>
      (await api.get<{ imports: ImportFile[] }>(endpoint("attendance"))).data
        .imports,
  });
  const upload = useMutation({
    mutationFn: async () => {
      const body = new FormData();
      queue.forEach((file, i) => body.append(`files[${i}]`, file));
      return (
        await api.post<{ data: ImportResult }>(
          endpoint("attendance/import"),
          body,
        )
      ).data.data;
    },
    onSuccess: (data) => {
      setResult(data);
      setQueue([]);
      [
        "attendance-workspace",
        "attendance",
        "attendance-import-history",
        "dashboard",
        "workflow",
        "audit",
      ].forEach((key) => void client.invalidateQueries({ queryKey: [key] }));

      const allDuplicates = data.files.every((f) => f.repeated_file);
      if (allDuplicates) {
        toast.warning(
          "This file was already imported before. No new punches were added.",
        );
      } else if (data.inserted === 0 && data.duplicates > 0) {
        toast.warning(
          `All ${data.duplicates} punches already exist — no new records added.`,
        );
      } else {
        toast.success(
          `${data.inserted} punches saved; ${data.duplicates} duplicates skipped.`,
        );
      }
    },
    onError: (e) => toast.error(detail(e)),
  });
  return (
    <section className="panel attendance-upload">
      <h2>Upload attendance files</h2>
      <p>
        Add TXT, DAT or CSV files (up to 16 files, 10 MB each). Files are
        combined using the <strong>enrollment ID</strong> set in each employee record.
        No machine selection or registration is required.
      </p>
      <div className="info-banner" style={{ marginBottom: 12 }}>
        <Upload size={20} />
        <div>
          <strong>Before uploading — set enrollment IDs in employee records</strong>
          <p>
            Each fingerprint machine stores employees by a numeric ID (e.g. <code>301110</code>).
            For this ministry, the <strong>Employee No.</strong> (e.g. <code>400046</code>) is the same number
            used by the fingerprint machine. The system automatically matches punches by Employee No.
            If a custom Enrollment ID is set in the Attendance tab, that takes priority.
            Punches for IDs not registered here are silently skipped.
          </p>
        </div>
      </div>
      <p>
        For machine exports with state 255 (ZKTeco format), the first and last scans
        of each day are treated as IN and OUT. A single scan stays in review.
        CSV files with explicit IN/OUT values are also supported.
      </p>
      <Field label="Attendance files">
        <input
          type="file"
          multiple
          accept=".txt,.dat,.csv"
          disabled={upload.isPending}
          onChange={(e) => {
            const added = Array.from(e.target.files || []);
            if (queue.length + added.length > 16)
              toast.error("Select at most 16 files per batch.");
            else if (added.some((file) => file.size > 10 * 1024 * 1024))
              toast.error("Each file must be 10 MB or smaller.");
            else setQueue((files) => [...files, ...added]);
            e.target.value = "";
          }}
        />
      </Field>
      {!!queue.length && (
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>File</th>
                <th>Size</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              {queue.map((file, i) => (
                <tr key={`${file.name}-${i}`}>
                  <td>{file.name}</td>
                  <td>{(file.size / 1024).toFixed(1)} KB</td>
                  <td>
                    <button
                      className="icon-button delete"
                      aria-label={`Remove ${file.name}`}
                      disabled={upload.isPending}
                      onClick={() =>
                        setQueue((files) =>
                          files.filter((_, index) => index !== i),
                        )
                      }
                    >
                      <Trash2 size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <div className="attendance-actions">
        <button
          className="button primary"
          disabled={!queue.length || upload.isPending}
          onClick={() => upload.mutate()}
        >
          <Upload size={17} />
          {upload.isPending
            ? "Reading & evaluating..."
            : "Import selected files & evaluate"}
        </button>
      </div>
      {upload.isError && (
        <State
          title="Upload failed or could not be confirmed"
          description={detail(upload.error)}
        />
      )}
      {result && (
        <div className="import-results" role="status">
          {result.files.every((f) => f.repeated_file) ? (
            <div className="duplicate-file-warning">
              <span className="duplicate-file-icon">⚠</span>
              <div>
                <strong>File already imported</strong>
                <p>
                  This file has been uploaded before. The exact same records
                  are already saved — no new punches were added.
                </p>
              </div>
            </div>
          ) : (
            <h3>
              {result.inserted} new{" "}
              {result.inserted === 1 ? "punch" : "punches"};{" "}
              {result.duplicates} duplicates skipped
            </h3>
          )}
          <p>{result.evaluated_days} employee days evaluated after combining all files.</p>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>File</th>
                  <th>New punches</th>
                  <th>Duplicates</th>
                  <th>Result</th>
                </tr>
              </thead>
              <tbody>
                {result.files.map((file, i) => (
                  <tr
                    key={`${file.id}-${i}`}
                    className={file.repeated_file ? "duplicate-file-row" : ""}
                  >
                    <td>{file.filename}</td>
                    <td>{file.inserted}</td>
                    <td>{file.duplicates}</td>
                    <td>
                      {file.repeated_file ? (
                        <span className="status neutral">⚠ Already imported</span>
                      ) : (
                        <span className="status active">Imported</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
      {history.isError && (
        <State
          title="Import history could not load"
          error={history.error}
          retry={() => void history.refetch()}
        />
      )}
      {!!history.data?.length && (
        <details>
          <summary>Recent file imports ({history.data.length})</summary>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>File</th>
                  <th>Imported</th>
                  <th>New punches</th>
                  <th>Duplicates</th>
                </tr>
              </thead>
              <tbody>
                {history.data.map((file) => (
                  <tr key={file.id}>
                    <td>{file.filename}</td>
                    <td>{file.created_at}</td>
                    <td>{file.inserted}</td>
                    <td>{file.duplicates}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      )}
    </section>
  );
}
