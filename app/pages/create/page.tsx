"use client";

import { useState, useRef, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Sparkles, FileText, Image, Camera, Loader2, ArrowLeft } from "lucide-react";
import Link from "next/link";

export default function CreatePage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<any>(null);

  const [pageName, setPageName] = useState("");
  const [pageUsername, setPageUsername] = useState("");
  const [category, setCategory] = useState("Cộng đồng");
  const [bio, setBio] = useState("");
  const [avatarUrl, setAvatarUrl] = useState("");
  
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState("");
  const fileInputRef = useRef<HTMLInputElement>(null);

  // 1. Fetch user data
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
      })
      .catch(() => {
        window.location.href = "/login";
      });
  }, []);

  const categories = [
    "Cộng đồng",
    "Doanh nghiệp / Cửa hàng",
    "Nghệ sĩ / Người công chúng",
    "Giải trí / Blog cá nhân",
    "Công nghệ / Khoa học",
    "Giáo dục / Trường học",
  ];

  // 2. Upload avatar của trang lên R2
  const handleAvatarChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);
    setError("");
    const formData = new FormData();
    formData.append("file", file);

    try {
      const res = await fetch("/api/upload", {
        method: "POST",
        body: formData,
      });

      if (res.ok) {
        const data = await res.json();
        setAvatarUrl(data.url);
      } else {
        const errData = await res.json();
        setError(errData.error || "Lỗi tải ảnh đại diện trang lên Cloudflare R2.");
      }
    } catch (err) {
      setError("Không thể kết nối đến máy chủ.");
    } finally {
      setUploading(false);
    }
  };

  // 3. Submit tạo trang
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");

    if (!pageName.trim() || !pageUsername.trim()) {
      setError("Vui lòng nhập đầy đủ thông tin bắt buộc.");
      return;
    }

    setLoading(true);

    try {
      const res = await fetch("/api/pages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          pageName,
          pageUsername,
          category,
          bio,
          avatarFilename: avatarUrl || undefined,
        }),
      });

      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "Tạo trang mới thất bại.");
      } else {
        // Tự động chuyển vai trò hoạt động sang Page vừa tạo
        const identityValue = JSON.stringify({ type: "page", id: data.page.id });
        document.cookie = `frest_identity=${encodeURIComponent(identityValue)}; path=/; max-age=2592000`;
        
        alert("Tạo trang mới thành công! Bạn đang hoạt động dưới vai trò Trang.");
        router.push(`/page/${data.page.pageUsername}`);
        router.refresh();
      }
    } catch (err) {
      setError("Không thể kết nối đến máy chủ.");
    } finally {
      setLoading(false);
    }
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto space-y-6">
      {/* Header */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-5 shadow-premium flex items-center justify-between relative overflow-hidden">
        <div className="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-2xl" />
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0 shadow-sm">
            <FileText className="w-6 h-6" />
          </div>
          <div>
            <h2 className="font-extrabold text-lg">Tạo Trang (Fanpage) mới</h2>
            <p className="text-xs text-gray-400 font-medium">Bắt đầu xây dựng cộng đồng hoặc trang doanh nghiệp của riêng bạn</p>
          </div>
        </div>
        <Link href="/" className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400">
          <ArrowLeft className="w-5 h-5" />
        </Link>
      </div>

      {/* Form */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 sm:p-8 shadow-premium space-y-6 relative overflow-hidden">
        {error && (
          <div className="flex items-center gap-2.5 p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold">
            <span>⚠️ {error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">
          
          {/* Avatar Upload */}
          <div className="flex flex-col items-center justify-center space-y-3">
            <div className="relative group cursor-pointer" onClick={() => fileInputRef.current?.click()}>
              <img
                src={avatarUrl || "/assets/images/icons/icon-192x192.png"}
                alt="Page avatar"
                className="w-24 h-24 rounded-2xl object-cover border-4 border-gray-100 dark:border-[#202024] shadow-md group-hover:opacity-80 transition-all"
                onError={(e) => {
                  e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                }}
              />
              <div className="absolute inset-0 flex items-center justify-center bg-black/40 text-white rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity">
                <Camera className="w-6 h-6" />
              </div>
              
              {uploading && (
                <div className="absolute inset-0 bg-black/60 backdrop-blur-sm rounded-2xl flex items-center justify-center text-primary">
                  <Loader2 className="w-6 h-6 animate-spin" />
                </div>
              )}
            </div>
            
            <input
              type="file"
              ref={fileInputRef}
              onChange={handleAvatarChange}
              accept="image/*"
              className="hidden"
            />
            
            <p className="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
              {uploading ? "Đang truyền tải R2..." : "Bấm ảnh để tải lên logo trang"}
            </p>
          </div>

          {/* Tên trang */}
          <div className="space-y-1.5">
            <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
              Tên trang Fanpage *
            </label>
            <input
              type="text"
              required
              placeholder="Ví dụ: Hội Lập Trình Next.js"
              value={pageName}
              onChange={(e) => setPageName(e.target.value)}
              className="w-full px-4 py-3 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium"
            />
          </div>

          {/* Username trang */}
          <div className="space-y-1.5">
            <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
              Tên người dùng trang (Username viết liền, không dấu) *
            </label>
            <div className="relative">
              <span className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-xs text-gray-400 font-bold">
                @
              </span>
              <input
                type="text"
                required
                placeholder="hoilaptrinh_nextjs"
                value={pageUsername}
                onChange={(e) => setPageUsername(e.target.value)}
                className="w-full pl-8 pr-4 py-3 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium"
              />
            </div>
          </div>

          {/* Danh mục */}
          <div className="space-y-1.5">
            <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
              Danh mục trang
            </label>
            <select
              value={category}
              onChange={(e) => setCategory(e.target.value)}
              className="w-full px-3.5 py-3 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium"
            >
              {categories.map((cat) => (
                <option key={cat} value={cat}>
                  {cat}
                </option>
              ))}
            </select>
          </div>

          {/* Mô tả (Bio) */}
          <div className="space-y-1.5">
            <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
              Mô tả ngắn về trang
            </label>
            <textarea
              placeholder="Chia sẻ mục đích hoặc thông tin trang..."
              value={bio}
              onChange={(e) => setBio(e.target.value)}
              rows={3}
              className="w-full px-4 py-3 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl focus:outline-none focus:border-primary transition-all font-medium resize-none"
            />
          </div>

          {/* Submit */}
          <button
            type="submit"
            disabled={loading || uploading}
            className="w-full py-3.5 bg-primary hover:bg-primary-hover text-white rounded-2xl font-bold text-sm shadow-premium flex items-center justify-center gap-2 disabled:opacity-50 transition-all"
          >
            {loading ? "Đang tạo trang..." : "Tạo trang ngay"}
          </button>
        </form>
      </div>
    </div>
  );
}
