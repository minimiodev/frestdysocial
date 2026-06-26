"use client";

import { useState, useEffect } from "react";
import { useSearchParams, useRouter } from "next/navigation";
import Link from "next/link";

export default function ResetPasswordPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get("token") || "";

  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!token) {
      setError("Mã khôi phục không tìm thấy. Vui lòng kiểm tra lại liên kết trong email.");
    }
  }, [token]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setMessage("");

    if (!token) {
      setError("Mã khôi phục không hợp lệ.");
      return;
    }

    if (password.length < 6) {
      setError("Mật khẩu mới phải dài tối thiểu 6 ký tự.");
      return;
    }

    if (password !== confirmPassword) {
      setError("Mật khẩu nhập lại không trùng khớp.");
      return;
    }

    setLoading(true);

    try {
      const res = await fetch("/api/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token, password }),
      });

      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "Đặt lại mật khẩu thất bại.");
      } else {
        setMessage(data.message || "Đặt lại mật khẩu thành công!");
        // Tự động chuyển về trang login sau 3 giây
        setTimeout(() => {
          router.push("/login");
        }, 3000);
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
            Đặt lại mật khẩu
          </h2>
          <p className="text-xs text-gray-400 font-medium px-4">
            Nhập mật khẩu bảo mật mới cho tài khoản Frest
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
            {message} Vui lòng đợi chuyển hướng...
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-3">
            <input
              type="password"
              required
              disabled={!token || !!message}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Mật khẩu mới (Tối thiểu 6 ký tự)"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
            <input
              type="password"
              required
              disabled={!token || !!message}
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Nhập lại mật khẩu mới"
              className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
            />
          </div>

          <button
            type="submit"
            disabled={loading || !token || !!message}
            className="w-full py-3.5 bg-[#1877f2] hover:bg-[#1565c0] active:scale-[0.99] text-white rounded-full font-bold text-sm transition-all disabled:opacity-50 shadow-md"
          >
            {loading ? "Đang xử lý..." : "Cập nhật mật khẩu"}
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
        </div>

      </div>
    </div>
  );
}
