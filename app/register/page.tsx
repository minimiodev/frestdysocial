"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";

export default function RegisterPage() {
  const [username, setUsername] = useState("");
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const res = await fetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, fullName, email, password }),
      });

      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "Đăng ký không thành công. Vui lòng kiểm tra lại thông tin.");
      } else {
        router.push("/");
        router.refresh();
      }
    } catch (err) {
      setError("Không thể kết nối đến máy chủ. Vui lòng thử lại sau.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-[75vh] flex items-center justify-center p-4">
      <div className="w-full max-w-[400px] bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 sm:p-8 shadow-premium space-y-6">
        
        {/* Header */}
        <div className="text-center space-y-1">
          <h2 className="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100">
            Tạo tài khoản mới
          </h2>
        </div>

        {/* Error notification */}
        {error && (
          <div className="p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold text-center animate-shake">
            {error}
          </div>
        )}

        {/* Form register */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-3">
            <input
              type="text"
              required
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Tên tài khoản (username)"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
            <input
              type="text"
              required
              value={fullName}
              onChange={(e) => setFullName(e.target.value)}
              placeholder="Họ và tên đầy đủ"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Email"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Mật khẩu"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full py-3.5 bg-[#1877f2] hover:bg-[#1565c0] active:scale-[0.99] text-white rounded-full font-bold text-sm transition-all disabled:opacity-50 shadow-md"
          >
            {loading ? "Đang tạo tài khoản..." : "Đăng ký"}
          </button>
        </form>

        <div className="text-center space-y-4">
          <div className="border-t border-[var(--card-border)] w-full" />

          <Link
            href="/login"
            className="inline-block w-full py-3.5 border border-[#1877f2] hover:bg-[#1877f2]/5 text-[#1877f2] rounded-full font-bold text-sm text-center transition-all active:scale-[0.99]"
          >
            Đăng nhập ngay
          </Link>
        </div>

      </div>
    </div>
  );
}
