"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ShieldAlert, User, Lock, AlertCircle, ArrowRight } from "lucide-react";

export default function AdminLoginPage() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const res = await fetch("/api/admin/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password }),
      });

      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "Tên đăng nhập hoặc mật khẩu không chính xác.");
      } else {
        router.push("/admin");
        router.refresh();
      }
    } catch (err) {
      setError("Không thể kết nối đến máy chủ. Vui lòng thử lại sau.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-[80vh] flex items-center justify-center p-4">
      <div className="w-full max-w-md bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-8 shadow-premium space-y-6 relative overflow-hidden">
        {/* Decorative Blur Backgrounds */}
        <div className="absolute -top-12 -right-12 w-32 h-32 bg-primary/10 rounded-full blur-3xl" />
        <div className="absolute -bottom-12 -left-12 w-32 h-32 bg-red-500/10 rounded-full blur-3xl" />

        {/* Branding header */}
        <div className="text-center space-y-2 relative z-10">
          <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-primary to-red-500 flex items-center justify-center mx-auto shadow-premium">
            <ShieldAlert className="w-6 h-6 text-white" />
          </div>
          <h2 className="text-2xl font-black bg-gradient-to-r from-primary to-red-500 bg-clip-text text-transparent">
            Frest Admin
          </h2>
          <p className="text-xs text-gray-400 font-medium">Trang đăng nhập của Quản trị viên hệ thống</p>
        </div>

        {/* Error notification */}
        {error && (
          <div className="flex items-center gap-2.5 p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold animate-shake">
            <AlertCircle className="w-5 h-5 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Form login */}
        <form onSubmit={handleSubmit} className="space-y-4.5 relative z-10">
          <div className="space-y-1.5">
            <label className="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
              Tên đăng nhập Admin
            </label>
            <div className="relative">
              <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <User className="h-4.5 w-4.5 text-gray-400" />
              </span>
              <input
                type="text"
                required
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="Nhập tên đăng nhập admin..."
                className="w-full pl-12 pr-4 py-3 text-sm bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[11px] font-bold text-gray-400 uppercase tracking-wider">
              Mật khẩu Admin
            </label>
            <div className="relative">
              <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <Lock className="h-4.5 w-4.5 text-gray-400" />
              </span>
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Nhập mật khẩu..."
                className="w-full pl-12 pr-4 py-3 text-sm bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-3.5 bg-gradient-to-r from-primary to-red-500 hover:from-primary-hover hover:to-red-600 text-white rounded-2xl font-bold text-sm shadow-premium transition-all active:scale-98 flex items-center justify-center gap-2 group disabled:opacity-50"
          >
            <span>{loading ? "Đang xác thực..." : "Đăng nhập hệ thống"}</span>
            {!loading && <ArrowRight className="w-4 h-4 transition-transform duration-200 group-hover:translate-x-0.5" />}
          </button>
        </form>

        <div className="text-center relative z-10">
          <Link href="/" className="text-xs text-gray-500 font-semibold hover:underline">
            ← Quay lại Trang chủ
          </Link>
        </div>
      </div>
    </div>
  );
}
