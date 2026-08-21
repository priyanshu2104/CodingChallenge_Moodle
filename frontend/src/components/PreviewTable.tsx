import { useState } from 'react';
import type { UploadResponse, UserRow } from '../types';

interface Props {
  data: UploadResponse;
  onImport: (users: UserRow[]) => void;
  onBack: () => void;
  loading: boolean;
}

type Filter = 'all' | 'valid' | 'invalid';

export default function PreviewTable({ data, onImport, onBack, loading }: Props) {
  const [filter, setFilter] = useState<Filter>('all');

  const visible = data.users.filter((u) => {
    if (filter === 'all') return true;
    return u.status === filter;
  });

  const validUsers = data.users.filter((u) => u.status === 'valid');

  return (
    <div>
      <p className="section-title">Preview Import</p>
      <p className="section-sub">
        Review the parsed records before importing. Only <strong>valid</strong> rows
        will be written to the database.
      </p>

      {/* Stats */}
      <div className="stats-bar">
        <div className="stat-chip total">
          <span>📋</span>
          <span>Found</span>
          <span className="stat-value">{data.total}</span>
        </div>
        <div className="stat-chip valid">
          <span>✓</span>
          <span>Valid</span>
          <span className="stat-value">{data.valid}</span>
        </div>
        <div className="stat-chip invalid">
          <span>✕</span>
          <span>Invalid</span>
          <span className="stat-value">{data.invalid}</span>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="filter-tabs" role="tablist" aria-label="Filter users">
        {(['all', 'valid', 'invalid'] as Filter[]).map((f) => (
          <button
            key={f}
            id={`filter-${f}`}
            className={`filter-tab ${filter === f ? 'active' : ''}`}
            role="tab"
            aria-selected={filter === f}
            onClick={() => setFilter(f)}
          >
            {f.charAt(0).toUpperCase() + f.slice(1)}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="table-wrapper">
        <table className="preview-table" aria-label="User preview table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Surname</th>
              <th>Email</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {visible.map((user) => (
              <tr
                key={`${user.line}-${user.email}`}
                className={user.status === 'invalid' ? 'row-invalid' : ''}
              >
                <td style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>
                  {user.line}
                </td>
                <td>{user.name || <em style={{ color: 'var(--text-muted)' }}>—</em>}</td>
                <td>{user.surname || <em style={{ color: 'var(--text-muted)' }}>—</em>}</td>
                <td style={{ fontFamily: 'monospace', fontSize: '0.82rem' }}>
                  {user.email || <em style={{ color: 'var(--text-muted)' }}>—</em>}
                </td>
                <td>
                  {user.status === 'valid' ? (
                    <span className="badge badge-valid">✓ Valid</span>
                  ) : (
                    <>
                      <span className="badge badge-invalid">✕ Invalid</span>
                      <ul className="error-list">
                        {user.errors.map((err, i) => (
                          <li key={i}>{err}</li>
                        ))}
                      </ul>
                    </>
                  )}
                </td>
              </tr>
            ))}

            {visible.length === 0 && (
              <tr>
                <td colSpan={5} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: '32px' }}>
                  No rows to display.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {/* Actions */}
      <div className="action-row">
        <button id="btn-back" className="btn btn-ghost" onClick={onBack}>
          ← Back
        </button>
        <button
          id="btn-import"
          className="btn btn-success"
          disabled={validUsers.length === 0 || loading}
          onClick={() => onImport(validUsers)}
        >
          {loading ? (
            <>
              <span className="spinner" />
              Importing…
            </>
          ) : (
            <>⬆ Import {validUsers.length} user{validUsers.length !== 1 ? 's' : ''}</>
          )}
        </button>
      </div>
    </div>
  );
}
