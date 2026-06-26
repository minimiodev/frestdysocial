"use client";

import { useState } from "react";
import Link from "next/link";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setMessage("");
    setLoading(true);

    try {
      const res = await fetch("/api/auth/forgot-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });

      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "Gửi email khôi phục mật khẩu thất bại.");
      } else {
        setMessage(data.message || "Đã gửi liên kết khôi phục mật khẩu tới email của bạn.");
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
            Quên mật khẩu?
          </h2>
          <p className="text-xs text-gray-400 font-medium px-4">
            Nhập email tài khoản để nhận liên kết khôi phục
          </p>
        </div>

        {/* Notifications */}
        {error && (
          <div className="p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold text-center animate-shake">
            {error}
          </div>
        )}

        {message && (
          <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/30 text-accent-green text-xs font-semibold text-center">
            {message}
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-3">
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Email tài khoản"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
          </div>

          <button
            type="submit"
            disabled={loading || !!message}
            className="w-full py-3.5 bg-[#1877f2] hover:bg-[#1565c0] active:scale-[0.99] text-white rounded-full font-bold text-sm transition-all disabled:opacity-50 shadow-md"
          >
            {loading ? "Đang gửi liên kết..." : "Gửi yêu cầu khôi phục"}
          </button>
        </form>

        <div className="text-center space-y-4">
          <div className="border-t border-[var(--card-border)] w-full" />

          <Link
            href="/login"
            className="inline-block w-full py-3.5 border border-[#1877f2] hover:bg-[#1877f2]/5 text-[#1877f2] rounded-full font-bold text-sm text-center transition-all active:scale-[0.99]"
          >
            Quay lại Đăng nhập
          </Link>
          
          <div className="text-xs text-gray-400">
            Chưa có tài khoản?{" "}
            <Link href="/register" className="text-[#1877f2] font-semibold hover:underline">
              Đăng ký ngay
            </Link>
          </div>
        </div>

      </div>
    </div>
  );
}
