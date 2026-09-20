import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthProvider';
import { ApiError } from '../lib/api';
import logo from '../assets/logo.png';

/**
 * Two steps in one screen: password, then the 2FA code when the account has
 * it. Keeping the challenge here rather than on its own route means a
 * refresh mid-challenge lands somewhere sensible instead of a dead URL.
 *
 * Palette is taken from the school crest: its black outline for the chrome,
 * its red for accents. No third colour is introduced.
 */
export default function Login() {
  const { user, awaitingTwoFactor } = useAuth();
  const location = useLocation();
  const from = (location.state as { from?: Location })?.from?.pathname ?? '/';

  if (user) return <Navigate to={from} replace />;

  return (
    <div className="grid min-h-screen lg:grid-cols-[5fr_4fr]">
      <aside className="hidden flex-col justify-between gap-12 border-r border-neutral-200 bg-white p-12 text-neutral-900 lg:flex">
        <img
          src={logo}
          alt="Hands-On Academy"
          className="w-full max-w-[230px]"
        />

        <div className="max-w-sm">
          <h1 className="font-serif text-4xl leading-tight text-neutral-900">
            Everything about every student, in one place.
          </h1>
          <p className="mt-4 text-sm leading-relaxed text-neutral-600">
            Enrolment, fees, marks and attendance for the 2026/2027 academic year.
          </p>
        </div>

        <p className="text-xs text-neutral-500">
          Trouble signing in? Contact the school office.
        </p>
      </aside>

      <main className="flex items-center justify-center bg-[#fafafa] px-6 py-12">
        <div className="w-full max-w-sm">
          {/* The crest again on small screens, where the left panel is hidden. */}
          <img
            src={logo}
            alt="Hands-On Academy"
            className="mb-8 w-40 lg:hidden"
          />
          {awaitingTwoFactor ? <TwoFactorStep /> : <PasswordStep />}
        </div>
      </main>
    </div>
  );
}

function PasswordStep() {
  const { login } = useAuth();
  const [form, setForm] = useState({ login: '', password: '', remember: false });
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);

    try {
      await login(form);
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Network error.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <h2 className="font-serif text-3xl text-neutral-900">Sign in</h2>
      <p className="mt-2 text-sm text-neutral-600">
        Use your staff email or student ID.
      </p>

      {error && !error.field('login') && (
        <p role="alert" className="mt-6 border-l-2 border-[#bf3c36] bg-[#bf3c36]/[0.06] px-3 py-2 text-sm text-[#8f2a25]">
          {error.message}
        </p>
      )}

      <div className="mt-8 space-y-5">
        <Field
          label="Email or student ID"
          name="login"
          autoComplete="username"
          value={form.login}
          error={error?.field('login')}
          onChange={(v) => setForm({ ...form, login: v })}
          onEnter={submit}
        />

        <div>
          <Field
            label="Password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={form.password}
            error={error?.field('password')}
            onChange={(v) => setForm({ ...form, password: v })}
            onEnter={submit}
          />
          <Link
            to="/forgot-password"
            className="mt-2 inline-block text-sm text-[#bf3c36] underline decoration-[#bf3c36]/30 underline-offset-4 hover:decoration-[#bf3c36]"
          >
            I forgot my password
          </Link>
        </div>

        <label className="flex items-center gap-2 text-sm text-neutral-700">
          <input
            type="checkbox"
            checked={form.remember}
            onChange={(e) => setForm({ ...form, remember: e.target.checked })}
            className="h-4 w-4 border-neutral-400 text-[#bf3c36] focus:ring-[#bf3c36]"
          />
          Keep me signed in on this device
        </label>

        <button
          onClick={submit}
          disabled={busy || !form.login || !form.password}
          className="w-full bg-[#bf3c36] px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-[#a5322d] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 disabled:cursor-not-allowed disabled:bg-neutral-300"
        >
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </div>
    </div>
  );
}

function TwoFactorStep() {
  const { submitTwoFactor, cancelTwoFactor } = useAuth();
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);

    try {
      await submitTwoFactor(useRecovery ? { recovery_code: recovery } : { code });
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Network error.'));
      setCode('');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <h2 className="font-serif text-3xl text-neutral-900">One more step</h2>
      <p className="mt-2 text-sm text-neutral-600">
        {useRecovery
          ? 'Enter one of the recovery codes you saved during setup.'
          : 'Enter the 6-digit code from your authenticator app.'}
      </p>

      {error && (
        <p role="alert" className="mt-6 border-l-2 border-[#bf3c36] bg-[#bf3c36]/[0.06] px-3 py-2 text-sm text-[#8f2a25]">
          {error.field('code') ?? error.message}
        </p>
      )}

      <div className="mt-8 space-y-5">
        {useRecovery ? (
          <Field
            label="Recovery code"
            name="recovery_code"
            value={recovery}
            onChange={setRecovery}
            onEnter={submit}
          />
        ) : (
          <div>
            <label htmlFor="code" className="block text-sm font-medium text-neutral-900">
              Verification code
            </label>
            <input
              id="code"
              name="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              autoFocus
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              onKeyDown={(e) => e.key === 'Enter' && code.length === 6 && submit()}
              className="mt-1.5 w-full border border-neutral-300 bg-white px-3 py-2.5 text-center text-2xl tracking-[0.4em] text-neutral-900 focus:border-[#bf3c36] focus:outline-none focus:ring-1 focus:ring-[#bf3c36]"
            />
          </div>
        )}

        <button
          onClick={submit}
          disabled={busy || (useRecovery ? !recovery : code.length !== 6)}
          className="w-full bg-[#bf3c36] px-4 py-3 text-sm font-medium text-white hover:bg-[#a5322d] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#191919] disabled:cursor-not-allowed disabled:bg-neutral-400"
        >
          {busy ? 'Checking…' : 'Verify'}
        </button>

        <div className="flex justify-between text-sm">
          <button
            onClick={() => { setUseRecovery(!useRecovery); setError(null); }}
            className="text-[#bf3c36] underline decoration-[#bf3c36]/30 underline-offset-4"
          >
            {useRecovery ? 'Use my authenticator app' : 'Use a recovery code'}
          </button>
          <button onClick={cancelTwoFactor} className="text-neutral-500 underline underline-offset-4">
            Start over
          </button>
        </div>
      </div>
    </div>
  );
}

interface FieldProps {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  onEnter?: () => void;
  type?: string;
  autoComplete?: string;
  error?: string;
}

function Field({
  label, name, value, onChange, onEnter, type = 'text', autoComplete, error,
}: FieldProps) {
  return (
    <div>
      <label htmlFor={name} className="block text-sm font-medium text-neutral-900">
        {label}
      </label>
      <input
        id={name}
        name={name}
        type={type}
        autoComplete={autoComplete}
        value={value}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${name}-error` : undefined}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={(e) => e.key === 'Enter' && onEnter?.()}
        className={`mt-1.5 w-full border bg-white px-3 py-2.5 text-neutral-900 focus:outline-none focus:ring-1 ${
          error
            ? 'border-[#bf3c36] focus:border-[#bf3c36] focus:ring-[#bf3c36]'
            : 'border-neutral-300 focus:border-[#bf3c36] focus:ring-[#bf3c36]'
        }`}
      />
      {error && (
        <p id={`${name}-error`} className="mt-1.5 text-sm text-[#8f2a25]">{error}</p>
      )}
    </div>
  );
}
