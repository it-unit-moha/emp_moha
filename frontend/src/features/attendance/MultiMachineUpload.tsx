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
      toast.success(
        `${data.inserted} punches saved; ${data.duplicates} duplicates skipped.`,
      );
    },
    onError: (e) => toast.error(detail(e)),
  });
  return (
    <section className="panel attendance-upload">
      <h2>Upload attendance files</h2>
      <p>
        Add TXT, DAT or CSV files (up to 16 files, 10 MB each). Files are
        combined using the enrollment ID in each employee record. No machine
        selection or registration is required.
      </p>
      <p>
        For attendance exports with state 255, the first and last distinct scans
        of each day are used as IN and OUT. A single scan remains for review.
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
          <h3>
            {result.inserted} new punches; {result.duplicates} duplicates
            skipped
          </h3>
          <p>
            {result.evaluated_days} employee days evaluated after combining all
            files.
          </p>
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
                  <tr key={`${file.id}-${i}`}>
                    <td>{file.filename}</td>
                    <td>{file.inserted}</td>
                    <td>{file.duplicates}</td>
                    <td>
                      {file.repeated_file ? "Already imported" : "Imported"}
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
