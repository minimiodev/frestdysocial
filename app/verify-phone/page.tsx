"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";

export default function VerifyPhonePage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<any>(null);

  const [phoneNumber, setPhoneNumber] = useState("");
  const [code, setCode] = useState("");
  const [step, setStep] = useState<1 | 2>(1); // 1: Nhập phone, 2: Nhập OTP
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  // 1. Fetch user data
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
        if (data.user.phoneNumber) {
          setPhoneNumber(data.user.phoneNumber);
        }
      })
      .catch(() => {
        router.push("/login");
      });
  }, [router]);

  // 2. Gửi OTP
  const handleSendOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSuccess("");
    setLoading(true);

    try {
      const res = await fetch("/api/auth/verify-phone", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ phoneNumber }),
      });

      const data = await res.json();
      if (res.ok) {
        setSuccess(data.message || "Mã OTP đã được gửi!");
        setStep(2);
      } else {
        setError(data.error || "Gửi OTP thất bại.");
      }
    } catch (e) {
      setError("Lỗi kết nối.");
    } finally {
      setLoading(false);
    }
  };

  // 3. Xác thực OTP
  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setSuccess("");
    setLoading(true);

    try {
      const res = await fetch("/api/auth/verify-phone", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ code }),
      });

      const data = await res.json();
      if (res.ok) {
        setSuccess(data.message || "Xác minh thành công!");
        setTimeout(() => {
          router.push(`/profile/${currentUser.username}`);
          router.refresh();
        }, 2000);
      } else {
        setError(data.error || "Mã xác thực OTP không chính xác.");
      }
    } catch (e) {
      setError("Lỗi kết nối.");
    } finally {
      setLoading(false);
    }
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-[#1877f2] border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-[75vh] flex items-center justify-center p-4">
      <div className="w-full max-w-[400px] bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 sm:p-8 shadow-premium space-y-6">
        
        {/* Header */}
        <div className="text-center space-y-1">
          <h2 className="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100">
            Xác minh điện thoại
          </h2>
          <p className="text-xs text-gray-400 font-medium px-4">
            Bảo mật tài khoản Frest bằng số điện thoại chính chủ
          </p>
        </div>

        {/* Status Messages */}
        {error && (
          <div className="p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold text-center animate-shake">
            {error}
          </div>
        )}

        {success && (
          <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/30 text-accent-green text-xs font-semibold text-center">
            {success}
          </div>
        )}

        {/* Step 1: Input Phone */}
        {step === 1 && (
          <form onSubmit={handleSendOtp} className="space-y-4">
            <div className="space-y-3">
              <input
                type="tel"
                required
                value={phoneNumber}
                onChange={(e) => setPhoneNumber(e.target.value)}
                placeholder="Số di động (Ví dụ: +84912345678)"
                className="w-full px-4 py-3.5 text-sm bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all font-medium text-gray-800 dark:text-gray-100 placeholder-gray-400"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3.5 bg-[#1877f2] hover:bg-[#1565c0] active:scale-[0.99] text-white rounded-full font-bold text-sm transition-all disabled:opacity-50 shadow-md"
            >
              {loading ? "Đang gửi OTP..." : "Nhận mã xác thực OTP"}
            </button>
          </form>
        )}

        {/* Step 2: Input OTP */}
        {step === 2 && (
          <form onSubmit={handleVerifyOtp} className="space-y-4">
            <div className="space-y-3">
              <input
                type="text"
                required
                maxLength={6}
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="Nhập mã OTP gồm 6 chữ số"
                className="w-full px-4 py-3.5 text-center text-lg font-black tracking-widest bg-transparent border border-gray-300 dark:border-gray-700 rounded-2xl focus:outline-none focus:border-[#1877f2] focus:ring-1 focus:ring-[#1877f2] transition-all text-gray-800 dark:text-gray-100 placeholder-gray-400"
              />
            </div>

            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setStep(1)}
                className="flex-1 py-3.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full font-bold text-sm transition-all active:scale-[0.99]"
              >
                Nhập lại SĐT
              </button>
              <button
                type="submit"
                disabled={loading || !!success}
                className="flex-1 py-3.5 bg-[#1877f2] hover:bg-[#1565c0] text-white rounded-full font-bold text-sm transition-all active:scale-[0.99] disabled:opacity-50 shadow-md"
              >
                {loading ? "Xác thực..." : "Xác minh"}
              </button>
            </div>
          </form>
        )}

        <div className="text-center space-y-4">
          <div className="border-t border-[var(--card-border)] w-full" />

          <Link
            href={`/profile/${currentUser.username}`}
            className="inline-block w-full py-3.5 border border-[#1877f2] hover:bg-[#1877f2]/5 text-[#1877f2] rounded-full font-bold text-sm text-center transition-all active:scale-[0.99]"
          >
            Quay lại trang cá nhân
          </Link>
        </div>

      </div>
    </div>
  );
}
