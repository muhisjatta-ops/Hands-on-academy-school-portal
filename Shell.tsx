import type { ReactNode } from 'react';
import { Link, useLocation } from 'react-router-dom';
import logo from '../assets/logo.png';
import { useAuth } from '../auth/AuthProvider';
import type { Permission } from '../types/auth';

const LINKS: { to: string; label: string; permission?: Permission }[] = [
  { to: '/',           label: 'Dashboard' },
  { to: '/students',   label: 'Students',   permission: 'students.view' },
  { to: '/fees',       label: 'Fees',       permission: 'fees.view' },
  { to: '/grades',     label: 'Grades',     permission: 'scores.enter' },
  { to: '/attendance', label: 'Attendance', permission: 'attendance.view' },
  { to: '/setup',      label: 'Setup',      permission: 'settings.manage' },
];

/**
 * The nav is filtered by permission, which is a courtesy to the user, not a
 * security control — every route behind it is permissioned server-side too.
 */
export function Shell({ children }: { children: ReactNode }) {
  const { user, logout, can } = useAuth();
  const location = useLocation();

  const visible = LINKS.filter((l) => !l.permission || can(l.permission));

  return (
    <div className="grid min-h-screen lg:grid-cols-[212px_1fr]">
      <nav className="flex flex-col border-r border-neutral-200 bg-white py-5 text-neutral-900">
        <div className="px-5 pb-5">
          <img
            src={logo}
            alt="Hands-On Academy"
            className="w-full max-w-[168px]"
          />
          <span className="mt-2 block text-xs text-neutral-500">Administration portal</span>
        </div>

        <div className="flex overflow-x-auto lg:flex-col">
          {visible.map((link) => {
            const active = link.to === '/'
              ? location.pathname === '/'
              : location.pathname.startsWith(link.to);

            return (
              <Link
                key={link.to}
                to={link.to}
                className={`whitespace-nowrap px-5 py-2.5 text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 lg:border-l-[3px] ${
                  active
                    ? 'bg-neutral-100 font-medium text-neutral-900 lg:border-[#bf3c36]'
                    : 'lg:border-transparent'
                }`}
              >
                {link.label}
              </Link>
            );
          })}
        </div>

        <div className="mt-auto px-5 pt-6 text-xs text-neutral-500">
          <p className="text-neutral-900">{user?.name}</p>
          <p>{user?.roles.join(', ')}</p>
          <button
            onClick={logout}
            className="mt-3 border border-neutral-300 px-3 py-1.5 text-neutral-800 hover:bg-neutral-100"
          >
            Sign out
          </button>
        </div>
      </nav>

      <main className="min-w-0 bg-[#fafafa] px-6 pb-16 pt-6 lg:px-8">{children}</main>
    </div>
  );
}

/* --------------------- small shared pieces --------------------- */

export function PageHead({
  title, subtitle, children,
}: { title: string; subtitle?: string; children?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="font-serif text-2xl text-neutral-900">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-neutral-600">{subtitle}</p>}
      </div>
      {children && <div className="flex gap-2">{children}</div>}
    </div>
  );
}

export function Stat({ label, value, note }: { label: string; value: string | number; note?: string }) {
  return (
    <div className="bg-white p-4">
      <p className="text-xs text-[#f0c4c0]">{label}</p>
      <p className="mt-0.5 font-serif text-2xl text-neutral-900">{value}</p>
      {note && <p className="text-xs text-[#f0c4c0]">{note}</p>}
    </div>
  );
}

export function StatRow({ children }: { children: ReactNode }) {
  return (
    <div className="grid gap-px border border-neutral-200 bg-neutral-200 sm:grid-cols-2 lg:grid-cols-4">
      {children}
    </div>
  );
}

export function Loading({ what = 'Loading' }: { what?: string }) {
  return <p className="py-10 text-center text-sm text-[#f0c4c0]">{what}…</p>;
}

export function Problem({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="border-l-2 border-[#bf3c36] bg-[#bf3c36]/[0.06] px-4 py-3 text-sm text-[#8f2a25]">
      <p>{message}</p>
      {onRetry && (
        <button onClick={onRetry} className="mt-2 underline underline-offset-4">
          Try again
        </button>
      )}
    </div>
  );
}

export function Empty({ message, children }: { message: string; children?: ReactNode }) {
  return (
    <div className="border border-dashed border-neutral-300 bg-white px-6 py-10 text-center">
      <p className="text-sm text-neutral-600">{message}</p>
      {children && <div className="mt-3">{children}</div>}
    </div>
  );
}

export const money = (amount: number) =>
  'D' + amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
