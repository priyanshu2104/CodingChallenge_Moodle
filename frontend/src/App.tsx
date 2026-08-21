import { useState } from 'react';
import './index.css';
import FileUpload from './components/FileUpload';
import PreviewTable from './components/PreviewTable';
import ImportResult from './components/ImportResult';
import type { ImportResponse, UploadResponse, UserRow } from './types';

type Step = 'upload' | 'preview' | 'result';

const STEPS: { key: Step; label: string }[] = [
  { key: 'upload',  label: 'Upload'  },
  { key: 'preview', label: 'Preview' },
  { key: 'result',  label: 'Import'  },
];

const API_BASE = '/api';

export default function App() {
  const [step, setStep]           = useState<Step>('upload');
  const [loading, setLoading]     = useState(false);
  const [error, setError]         = useState<string | null>(null);
  const [preview, setPreview]     = useState<UploadResponse | null>(null);
  const [importResult, setImport] = useState<ImportResponse | null>(null);

  // ── Step 1: Upload & parse ──────────────────────────────────────────────────
  const handleFileSelected = async (file: File) => {
    setError(null);
    setLoading(true);

    const form = new FormData();
    form.append('file', file);

    try {
      const res = await fetch(`${API_BASE}/upload`, { method: 'POST', body: form });
      const json = await res.json();

      if (!res.ok) {
        setError(json.error ?? `Server error ${res.status}`);
        return;
      }

      setPreview(json as UploadResponse);
      setStep('preview');
    } catch (e) {
      setError('Network error — is the PHP server running on localhost:8080?');
    } finally {
      setLoading(false);
    }
  };

  // ── Step 2: Import ──────────────────────────────────────────────────────────
  const handleImport = async (users: UserRow[]) => {
    setError(null);
    setLoading(true);

    try {
      const res = await fetch(`${API_BASE}/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ users }),
      });
      const json = await res.json();

      if (!res.ok) {
        setError(json.error ?? `Server error ${res.status}`);
        return;
      }

      setImport(json as ImportResponse);
      setStep('result');
    } catch (e) {
      setError('Network error — is the PHP server running on localhost:8080?');
    } finally {
      setLoading(false);
    }
  };

  // ── Reset ───────────────────────────────────────────────────────────────────
  const handleReset = () => {
    setStep('upload');
    setPreview(null);
    setImport(null);
    setError(null);
  };

  const stepIndex = STEPS.findIndex((s) => s.key === step);

  return (
    <div className="app-wrapper">
      <div className="app-container">

        {/* Header */}
        <header className="app-header">
          <div className="app-logo" aria-hidden="true">📥</div>
          <h1 className="app-title">User Import</h1>
          <p className="app-subtitle">Upload, validate, and import users from a CSV file</p>
        </header>

        {/* Step indicator */}
        <nav className="steps" aria-label="Progress steps">
          {STEPS.map((s, i) => {
            const status =
              i < stepIndex ? 'done' : i === stepIndex ? 'active' : '';
            return (
              <div key={s.key} style={{ display: 'flex', alignItems: 'center' }}>
                <div className={`step ${status}`} aria-current={status === 'active' ? 'step' : undefined}>
                  <span className="step-number">{i < stepIndex ? '✓' : i + 1}</span>
                  <span>{s.label}</span>
                </div>
                {i < STEPS.length - 1 && <div className="step-connector" aria-hidden="true" />}
              </div>
            );
          })}
        </nav>

        {/* Error alert */}
        {error && (
          <div className="alert alert-error" role="alert">
            <span>⚠</span>
            <span>{error}</span>
          </div>
        )}

        {/* Main card */}
        <main className="card">
          {step === 'upload' && (
            <FileUpload onFileSelected={handleFileSelected} loading={loading} />
          )}
          {step === 'preview' && preview && (
            <PreviewTable
              data={preview}
              onImport={handleImport}
              onBack={handleReset}
              loading={loading}
            />
          )}
          {step === 'result' && importResult && (
            <ImportResult result={importResult} onReset={handleReset} />
          )}
        </main>

      </div>
    </div>
  );
}
