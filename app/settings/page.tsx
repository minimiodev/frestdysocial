"use client";

import { useState, useEffect, useRef } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { 
  Settings, 
  User, 
  Lock, 
  ShieldAlert, 
  Trash2, 
  Camera, 
  Loader2, 
  Check, 
  Globe, 
  Info,
  Calendar
} from "lucide-react";
import useSWR from "swr";

const fetcher = (url: string) => fetch(url).then((res) => res.json());

export default function SettingsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const initialTab = searchParams.get("tab") || "profile";
  
  const [activeTab, setActiveTab] = useState(initialTab);
  const [currentUser, setCurrentUser] = useState<any>(null);

  // Profile Form States
  const [fullName, setFullName] = useState("");
  const [bio, setBio] = useState("");
  const [livesAt, setLivesAt] = useState("");
  const [country, setCountry] = useState("");
  const [workplace, setWorkplace] = useState("");
  const [gender, setGender] = useState("");
  const [isPrivate, setIsPrivate] = useState(false);
  const [showNsfw, setShowNsfw] = useState(false);
  const [avatarUrl, setAvatarUrl] = useState("");
  const [coverUrl, setCoverUrl] = useState("");
  const [uploading, setUploading] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);

  // Password Form States
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  // Name Request States
  const [firstName, setFirstName] = useState("");
  const [middleName, setMiddleName] = useState("");
  const [lastName, setLastName] = useState("");
  const [nameDisplayOrder, setNameDisplayOrder] = useState("last_middle_first");

  // Age Verification States
  const [dob, setDob] = useState("");
  const [idProofUrl, setIdProofUrl] = useState("");
  const [uploadingId, setUploadingId] = useState(false);

  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");

  const fileInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);
  const idInputRef = useRef<HTMLInputElement>(null);

  // 1. Fetch user data
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        const u = data.user;
        setCurrentUser(u);
        setFullName(u.fullName || "");
        setBio(u.bio || "");
        setLivesAt(u.livesAt || "");
        setCountry(u.country || "");
        setWorkplace(u.workplace || "");
        setGender(u.gender || "");
        setIsPrivate(u.isPrivate || false);
        setShowNsfw(u.showNsfw || false);
        setAvatarUrl(u.avatarFilename || "");
        setCoverUrl(u.coverFilename || "");
        setDob(u.dob ? u.dob.split("T")[0] : "");
      })
      .catch(() => {
        router.push("/login");
      });
  }, [router]);

  useEffect(() => {
    setActiveTab(initialTab);
  }, [initialTab]);

  const showNotification = (type: "success" | "error", msg: string) => {
    if (type === "success") {
      setSuccessMsg(msg);
      setErrorMsg("");
    } else {
      setErrorMsg(msg);
      setSuccessMsg("");
    }
    setTimeout(() => {
      setSuccessMsg("");
      setErrorMsg("");
    }, 4000);
  };

  // 2. Upload avatar R2
  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);
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
        // Lưu trực tiếp avatar mới
        await fetch("/api/users/settings", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "profile", avatarFilename: data.url }),
        });
        showNotification("success", "Cập nhật ảnh đại diện thành công!");
      } else {
        showNotification("error", "Lỗi tải ảnh lên Cloudflare R2.");
      }
    } catch (err) {
      showNotification("error", "Không thể kết nối máy chủ.");
    } finally {
      setUploading(false);
    }
  };

  // Upload cover R2
  const handleCoverUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingCover(true);
    const formData = new FormData();
    formData.append("file", file);

    try {
      const res = await fetch("/api/upload", {
        method: "POST",
        body: formData,
      });
      if (res.ok) {
        const data = await res.json();
        setCoverUrl(data.url);
        // Lưu trực tiếp cover mới
        await fetch("/api/users/settings", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "profile", coverFilename: data.url }),
        });
        showNotification("success", "Cập nhật ảnh bìa thành công!");
      } else {
        showNotification("error", "Lỗi tải ảnh bìa lên Cloudflare R2.");
      }
    } catch (err) {
      showNotification("error", "Không thể kết nối máy chủ.");
    } finally {
      setUploadingCover(false);
    }
  };

  // 3. Upload ảnh CMND R2
  const handleIdUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingId(true);
    const formData = new FormData();
    formData.append("file", file);

    try {
      const res = await fetch("/api/upload", {
        method: "POST",
        body: formData,
      });
      if (res.ok) {
        const data = await res.json();
        setIdProofUrl(data.url);
        showNotification("success", "Đã tải lên ảnh giấy tờ xác minh.");
      } else {
        showNotification("error", "Lỗi tải tệp lên.");
      }
    } catch (err) {
      showNotification("error", "Không thể kết nối.");
    } finally {
      setUploadingId(false);
    }
  };

  // 4. Save profile settings
  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);

    try {
      const res = await fetch("/api/users/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "profile",
          fullName,
          bio,
          livesAt,
          country,
          workplace,
          gender,
          isPrivate,
          showNsfw,
        }),
      });

      if (res.ok) {
        showNotification("success", "Cập nhật thông tin cá nhân thành công!");
        router.refresh();
      } else {
        const data = await res.json();
        showNotification("error", data.error || "Cập nhật hồ sơ thất bại.");
      }
    } catch (e) {
      showNotification("error", "Lỗi kết nối.");
    } finally {
      setSaving(false);
    }
  };

  // 5. Change Password
  const handleSavePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword !== confirmPassword) {
      showNotification("error", "Mật khẩu xác nhận không trùng khớp.");
      return;
    }

    setSaving(true);
    try {
      const res = await fetch("/api/users/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "password",
          currentPassword,
          newPassword,
        }),
      });

      if (res.ok) {
        showNotification("success", "Đã đổi mật khẩu thành công!");
        setCurrentPassword("");
        setNewPassword("");
        setConfirmPassword("");
      } else {
        const data = await res.json();
        showNotification("error", data.error || "Mật khẩu hiện tại không đúng.");
      }
    } catch (e) {
      showNotification("error", "Lỗi kết nối.");
    } finally {
      setSaving(false);
    }
  };

  // 6. Name Request
  const handleSaveNameRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);

    try {
      const res = await fetch("/api/users/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "name_request",
          firstName,
          middleName,
          lastName,
          nameDisplayOrder,
        }),
      });

      if (res.ok) {
        showNotification("success", "Yêu cầu thay đổi tên đã được gửi!");
        setFirstName("");
        setMiddleName("");
        setLastName("");
      } else {
        const data = await res.json();
        showNotification("error", data.error || "Gửi yêu cầu thất bại.");
      }
    } catch (e) {
      showNotification("error", "Lỗi kết nối.");
    } finally {
      setSaving(false);
    }
  };

  // 7. Age Verification
  const handleSaveAgeVerification = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!idProofUrl) {
      showNotification("error", "Vui lòng tải lên ảnh chụp giấy tờ xác minh.");
      return;
    }

    setSaving(true);
    try {
      const res = await fetch("/api/users/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "age_verification",
          dob,
          idProofFilename: idProofUrl,
        }),
      });

      if (res.ok) {
        showNotification("success", "Yêu cầu xác minh độ tuổi đã được gửi!");
        setIdProofUrl("");
      } else {
        const data = await res.json();
        showNotification("error", data.error || "Gửi xác thực thất bại.");
      }
    } catch (e) {
      showNotification("error", "Lỗi kết nối.");
    } finally {
      setSaving(false);
    }
  };

  // 8. Delete Account
  const handleDeleteAccount = async () => {
    if (!window.confirm("CẢNH BÁO NGUY HIỂM: Tài khoản của bạn và toàn bộ bài đăng, tin nhắn, câu chuyện sẽ bị XÓA VĨNH VIỄN và KHÔNG THỂ KHÔI PHỤC. Bạn có chắc chắn muốn tiếp tục không?")) return;
    
    setSaving(true);
    try {
      const res = await fetch("/api/users/delete-account", {
        method: "DELETE",
      });

      if (res.ok) {
        alert("Đã xóa tài khoản thành công. Tạm biệt bạn!");
        window.location.href = "/register";
      } else {
        showNotification("error", "Không thể xóa tài khoản.");
      }
    } catch (e) {
      showNotification("error", "Lỗi kết nối.");
    } finally {
      setSaving(false);
    }
  };

  if (!currentUser) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  const tabs = [
    { id: "profile", name: "Cá nhân", icon: User },
    { id: "password", name: "Mật khẩu", icon: Lock },
    { id: "name", name: "Đổi tên", icon: Globe },
    { id: "age", name: "Xác minh tuổi", icon: Calendar },
    { id: "danger", name: "Vùng nguy hiểm", icon: Trash2 },
  ];

  return (
    <div className="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-6">
      
      {/* Sidebar Tabs */}
      <div className="md:col-span-1 bg-[var(--card-bg)] border border-[var(--card-border)] rounded-2xl p-4 h-fit space-y-1 shadow-premium shrink-0">
        <h3 className="font-extrabold text-sm px-2.5 pb-3 border-b border-[var(--card-border)] mb-3 flex items-center gap-1.5">
          <Settings className="w-4 h-4 text-primary" />
          Cài đặt
        </h3>

        {tabs.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => {
                setActiveTab(tab.id);
                setErrorMsg("");
                setSuccessMsg("");
              }}
              className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-left transition-colors ${
                isActive 
                  ? "bg-primary/10 text-primary" 
                  : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
              }`}
            >
              <Icon className="w-4 h-4" />
              {tab.name}
            </button>
          );
        })}
      </div>

      {/* Main Settings Panel */}
      <div className="md:col-span-3 bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 sm:p-8 shadow-premium space-y-6 relative overflow-hidden">
        
        {/* Floating Notifications */}
        {successMsg && (
          <div className="p-3.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-950/30 text-accent-green text-xs font-bold rounded-2xl flex items-center gap-2">
            <Check className="w-4.5 h-4.5" />
            <span>{successMsg}</span>
          </div>
        )}

        {errorMsg && (
          <div className="p-3.5 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-950/30 text-accent-pink text-xs font-bold rounded-2xl flex items-center gap-2">
            <ShieldAlert className="w-4.5 h-4.5" />
            <span>{errorMsg}</span>
          </div>
        )}

        {/* Tab 1: Profile Details */}
        {activeTab === "profile" && (
          <form onSubmit={handleSaveProfile} className="space-y-5">
            {/* Cover & Avatar upload block */}
            <div className="border-b border-[var(--card-border)] pb-6">
              <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 block">
                Ảnh đại diện và Ảnh bìa
              </label>
              
              {/* Cover Photo */}
              <div 
                className="h-36 w-full rounded-2xl bg-gradient-to-r from-primary/30 via-accent-purple/20 to-accent-pink/30 relative overflow-hidden group cursor-pointer border border-[var(--card-border)]"
                onClick={() => coverInputRef.current?.click()}
              >
                {coverUrl && (
                  <img 
                    src={coverUrl.startsWith("http") ? coverUrl : `/uploads/covers/${coverUrl}`} 
                    alt="Cover" 
                    className="w-full h-full object-cover"
                    onError={(e) => {
                      e.currentTarget.style.display = 'none';
                    }}
                  />
                )}
                <div className="absolute inset-0 bg-black/40 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity gap-2">
                  <Camera className="w-5 h-5" />
                  <span className="text-xs font-bold">Thay đổi ảnh bìa</span>
                </div>
                {uploadingCover && (
                  <div className="absolute inset-0 bg-black/60 flex items-center justify-center text-primary">
                    <Loader2 className="w-6 h-6 animate-spin" />
                  </div>
                )}
              </div>
              <input
                type="file"
                ref={coverInputRef}
                onChange={handleCoverUpload}
                accept="image/*"
                className="hidden"
              />

              {/* Avatar Photo (Overlapping) */}
              <div className="flex items-end gap-4 px-4 -mt-10 relative z-10">
                <div 
                  className="relative group cursor-pointer" 
                  onClick={(e) => {
                    e.stopPropagation();
                    fileInputRef.current?.click();
                  }}
                >
                  <img
                    src={avatarUrl.startsWith("http") ? avatarUrl : `/uploads/avatars/${avatarUrl}`}
                    alt="Avatar"
                    className="w-20 h-20 rounded-2xl object-cover border-4 border-[var(--card-bg)] shadow-md ring-2 ring-gray-100/10"
                    onError={(e) => {
                      e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                    }}
                  />
                  <div className="absolute inset-0 bg-black/40 rounded-2xl flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity">
                    <Camera className="w-4.5 h-4.5" />
                  </div>
                  {uploading && (
                    <div className="absolute inset-0 bg-black/60 rounded-2xl flex items-center justify-center text-primary">
                      <Loader2 className="w-5 h-5 animate-spin" />
                    </div>
                  )}
                </div>
                <input
                  type="file"
                  ref={fileInputRef}
                  onChange={handleAvatarUpload}
                  accept="image/*"
                  className="hidden"
                />
                <div className="mb-2">
                  <h4 className="font-extrabold text-sm flex items-center gap-1.5 text-gray-800 dark:text-gray-200">
                    <span>{fullName}</span>
                  </h4>
                  <p className="text-[9px] text-gray-400 font-bold uppercase tracking-wider">
                    {uploading ? "Đang tải ảnh đại diện..." : uploadingCover ? "Đang tải ảnh bìa..." : "Nhấp vào ảnh để thay đổi"}
                  </p>
                </div>
              </div>
            </div>

            {/* Form inputs */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5 col-span-2">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tên hiển thị</label>
                <input
                  type="text"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5 col-span-2">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tiểu sử (Bio)</label>
                <textarea
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  rows={2}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium resize-none"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nơi sống</label>
                <input
                  type="text"
                  value={livesAt}
                  onChange={(e) => setLivesAt(e.target.value)}
                  placeholder="Ví dụ: Hà Nội"
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quốc gia</label>
                <input
                  type="text"
                  value={country}
                  onChange={(e) => setCountry(e.target.value)}
                  placeholder="Ví dụ: Việt Nam"
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nơi làm việc</label>
                <input
                  type="text"
                  value={workplace}
                  onChange={(e) => setWorkplace(e.target.value)}
                  placeholder="Ví dụ: Google"
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Giới tính</label>
                <select
                  value={gender}
                  onChange={(e) => setGender(e.target.value)}
                  className="w-full px-3 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                >
                  <option value="">Không chọn</option>
                  <option value="male">Nam</option>
                  <option value="female">Nữ</option>
                  <option value="other">Khác</option>
                </select>
              </div>

              {/* Toggles */}
              <div className="col-span-2 pt-3 flex flex-col gap-3">
                <label className="flex items-center gap-3 cursor-pointer text-xs font-semibold">
                  <input
                    type="checkbox"
                    checked={showNsfw}
                    onChange={(e) => setShowNsfw(e.target.checked)}
                    className="rounded text-primary focus:ring-primary h-4 w-4"
                  />
                  Hiển thị nội dung nhạy cảm 18+ (NSFW)
                </label>
                
                <label className="flex items-center gap-3 cursor-pointer text-xs font-semibold">
                  <input
                    type="checkbox"
                    checked={isPrivate}
                    onChange={(e) => setIsPrivate(e.target.checked)}
                    className="rounded text-primary focus:ring-primary h-4 w-4"
                  />
                  Tài khoản riêng tư (Chỉ cho phép người theo dõi xem bài viết)
                </label>
              </div>
            </div>

            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
            >
              {saving ? "Đang lưu..." : "Lưu thay đổi"}
            </button>
          </form>
        )}

        {/* Tab 2: Change Password */}
        {activeTab === "password" && (
          <form onSubmit={handleSavePassword} className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mật khẩu hiện tại</label>
              <input
                type="password"
                required
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
              />
            </div>
            
            <div className="space-y-1.5">
              <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mật khẩu mới</label>
              <input
                type="password"
                required
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="Tối thiểu 6 ký tự"
                className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Xác nhận mật khẩu mới</label>
              <input
                type="password"
                required
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="Nhập lại mật khẩu mới"
                className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
              />
            </div>

            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
            >
              {saving ? "Đang cập nhật..." : "Đổi mật khẩu"}
            </button>
          </form>
        )}

        {/* Tab 3: Name Request Change */}
        {activeTab === "name" && (
          <form onSubmit={handleSaveNameRequest} className="space-y-4">
            <div className="p-4 bg-blue-50/50 dark:bg-blue-950/15 border border-blue-100 dark:border-blue-950/30 rounded-2xl text-xs text-blue-600 dark:text-blue-400 flex gap-2.5">
              <Info className="w-5 h-5 shrink-0" />
              <p>Để đảm bảo tính xác thực, yêu cầu thay đổi Họ và Tên của bạn cần được Admin kiểm tra và xét duyệt trước khi hiển thị chính thức trên hệ thống.</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Họ</label>
                <input
                  type="text"
                  required
                  placeholder="Ví dụ: Nguyễn"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tên đệm</label>
                <input
                  type="text"
                  placeholder="Ví dụ: Hoàng"
                  value={middleName}
                  onChange={(e) => setMiddleName(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tên</label>
                <input
                  type="text"
                  required
                  placeholder="Ví dụ: Dũng"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              <div className="space-y-1.5 col-span-3">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Định dạng hiển thị tên</label>
                <select
                  value={nameDisplayOrder}
                  onChange={(e) => setNameDisplayOrder(e.target.value)}
                  className="w-full px-3 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                >
                  <option value="last_middle_first">Họ + Tên đệm + Tên (Việt Nam)</option>
                  <option value="first_middle_last">Tên + Tên đệm + Họ (Quốc tế)</option>
                </select>
              </div>
            </div>

            <button
              type="submit"
              disabled={saving}
              className="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
            >
              {saving ? "Đang gửi..." : "Gửi yêu cầu duyệt"}
            </button>
          </form>
        )}

        {/* Tab 4: Age Verification */}
        {activeTab === "age" && (
          <form onSubmit={handleSaveAgeVerification} className="space-y-4">
            <div className="p-4 bg-purple-50/50 dark:bg-purple-950/15 border border-purple-100 dark:border-purple-950/30 rounded-2xl text-xs text-purple-600 dark:text-purple-400 flex gap-2.5">
              <Info className="w-5 h-5 shrink-0" />
              <div>
                <p className="font-bold mb-0.5">Xác minh độ tuổi 18+ (CCCD/Hộ chiếu)</p>
                <p>Tải lên ảnh chụp giấy tờ tùy thân của bạn cùng với ngày sinh chính xác để mở khóa hiển thị toàn bộ nội dung NSFW nhạy cảm trên mạng xã hội.</p>
              </div>
            </div>

            <div className="space-y-3">
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Ngày sinh của bạn</label>
                <input
                  type="date"
                  required
                  value={dob}
                  onChange={(e) => setDob(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary font-medium"
                />
              </div>

              {/* Upload R2 ID Proof */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Giấy tờ tùy thân (Ảnh chụp)</label>
                <div
                  onClick={() => idInputRef.current?.click()}
                  className="border-2 border-dashed border-[var(--card-border)] rounded-2xl p-6 text-center hover:bg-gray-50 dark:hover:bg-[#18181c] transition-all cursor-pointer relative"
                >
                  {idProofUrl ? (
                    <div className="space-y-2">
                      <img src={idProofUrl} alt="ID Proof" className="max-h-28 mx-auto rounded-lg" />
                      <p className="text-[10px] text-accent-green font-bold">✓ Đã chọn ảnh thành công (Bấm để chọn lại)</p>
                    </div>
                  ) : (
                    <div className="space-y-1 text-xs text-gray-400">
                      <Camera className="w-6 h-6 mx-auto text-gray-400" />
                      <p className="font-bold">Bấm để tải ảnh CCCD/Hộ chiếu lên R2</p>
                      <p className="text-[9px]">Chấp nhận định dạng ảnh JPG, PNG</p>
                    </div>
                  )}

                  {uploadingId && (
                    <div className="absolute inset-0 bg-black/60 rounded-2xl flex items-center justify-center text-primary">
                      <Loader2 className="w-6 h-6 animate-spin" />
                    </div>
                  )}
                </div>

                <input
                  type="file"
                  ref={idInputRef}
                  onChange={handleIdUpload}
                  accept="image/*"
                  className="hidden"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={saving || uploadingId}
              className="px-5 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold shadow-premium"
            >
              {saving ? "Đang gửi..." : "Gửi tài liệu xác thực"}
            </button>
          </form>
        )}

        {/* Tab 5: Danger Zone (Delete account) */}
        {activeTab === "danger" && (
          <div className="space-y-5">
            <div className="p-4 bg-red-50/50 dark:bg-red-950/15 border border-red-100 dark:border-red-950/30 rounded-2xl text-xs text-accent-pink flex gap-2.5">
              <ShieldAlert className="w-5 h-5 shrink-0" />
              <div>
                <h4 className="font-bold uppercase tracking-wider mb-1">Cảnh báo khu vực nguy hiểm</h4>
                <p>Mọi hành động ở đây đều là vĩnh viễn và không thể khôi phục. Dữ liệu tài khoản của bạn sẽ bị dọn dẹp sạch sẽ khỏi máy chủ Frest.</p>
              </div>
            </div>

            <div className="p-4 border border-red-200 dark:border-red-950/40 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <h5 className="font-bold text-xs">Xóa tài khoản vĩnh viễn</h5>
                <p className="text-[10px] text-gray-400 font-semibold mt-0.5">Xóa toàn bộ bài viết, stories, tin nhắn và thông tin cá nhân của bạn.</p>
              </div>
              <button
                onClick={handleDeleteAccount}
                disabled={saving}
                className="px-4.5 py-2.5 bg-accent-pink hover:bg-red-600 text-white rounded-xl text-xs font-bold shadow-md transition-all active:scale-95 shrink-0"
              >
                Xóa vĩnh viễn
              </button>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
