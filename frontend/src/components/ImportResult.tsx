import type { ImportResponse } from '../types';

interface Props {
  result: ImportResponse;
  onReset: () => void;
}

export default function ImportResult({ result, onReset }: Props) {
  const allGood = result.errors.length === 0 && result.skipped === 0;

  return (
    <div className="result-card">
      <span className="result-icon" role="img" aria-label="Result">
        {allGood ? '🎉' : '✅'}
      </span>

      <h2 className="result-title">
        {allGood ? 'Import Successful!' : 'Import Complete'}
      </h2>
      <p className="result-subtitle">
        {result.inserted} user{result.inserted !== 1 ? 's' : ''} have been added to the database.
      </p>

      <div className="result-stats">
        <div className="result-stat">
          <div className="result-stat-value inserted">{result.inserted}</div>
          <div className="result-stat-label">Inserted</div>
        </div>
        <div className="result-stat">
          <div className="result-stat-value skipped">{result.skipped}</div>
          <div className="result-stat-label">Already in DB</div>
        </div>
      </div>

      {result.errors.length > 0 && (
        <div className="db-errors">
          <h4>ℹ️ These emails already existed in the database and were skipped</h4>
          <ul>
            {result.errors.map((e, i) => (
              <li key={i}>
                Line {e.line} — <code>{e.email}</code>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div style={{ marginTop: '32px' }}>
        <button id="btn-reset" className="btn btn-primary" onClick={onReset}>
          ↩ Import Another File
        </button>
      </div>
    </div>
  );
}
