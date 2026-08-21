import { useRef, useState } from 'react';
import type { DragEvent, ChangeEvent } from 'react';

interface Props {
  onFileSelected: (file: File) => void;
  loading: boolean;
}

export default function FileUpload({ onFileSelected, loading }: Props) {
  const [dragOver, setDragOver] = useState(false);
  const [fileName, setFileName] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFile = (file: File) => {
    setFileName(file.name);
    onFileSelected(file);
  };

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) handleFile(file);
  };

  const handleDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setDragOver(false);
    const file = e.dataTransfer.files?.[0];
    if (file) handleFile(file);
  };

  return (
    <div>
      <p className="section-title">Upload CSV File</p>
      <p className="section-sub">
        Your CSV must contain <strong>name</strong>, <strong>surname</strong>, and{' '}
        <strong>email</strong> columns.
      </p>

      <div
        className={`drop-zone ${dragOver ? 'drag-over' : ''}`}
        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => inputRef.current?.click()}
      >
        <span className="drop-zone-icon">📂</span>
        <p className="drop-zone-label">
          {dragOver ? 'Drop to upload' : 'Drag & drop your CSV here'}
        </p>
        <p className="drop-zone-sub">or click to browse files</p>
        <input
          ref={inputRef}
          type="file"
          accept=".csv,text/csv"
          onChange={handleChange}
          onClick={(e) => e.stopPropagation()}
        />
      </div>

      {fileName && (
        <div className="file-chosen">
          <span>📄</span>
          <span>{fileName}</span>
        </div>
      )}

      <div className="action-row">
        <button
          id="btn-upload"
          className="btn btn-primary"
          disabled={!fileName || loading}
          onClick={() => {
            const file = inputRef.current?.files?.[0];
            if (file) handleFile(file);
          }}
        >
          {loading ? (
            <>
              <span className="spinner" />
              Parsing…
            </>
          ) : (
            <>📊 Parse &amp; Preview</>
          )}
        </button>
      </div>
    </div>
  );
}
