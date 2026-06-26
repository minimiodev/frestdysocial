"use client";

import { useState, useRef } from "react";
import { useRouter } from "next/navigation";
import { User, Settings, ShieldAlert, Lock, Save, KeyRound, Camera, Loader2 } from "lucide-react";

interface ProfileClientProps {
  isFollowing?: boolean;
  targetUsername: string;
  isSetting: boolean;
  isOwnProfile?: boolean;
  userObj?: any;
  children?: React.ReactNode;
}

export default function ProfileClient({
  isFollowing: initialFollowing = false,
  targetUsername,
  isSetting,
  isOwnProfile = false,
  userObj,
  children,
}: ProfileClientProps) {
  const [following, setFollowing] = useState(initialFollowing);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<"posts" | "info" | "name" | "age" | "password">("posts");
  const router = useRouter();

  // Settings states
  const [fullName, setFullName] = useState(userObj?.fullName || "");
  const [bio, setBio] = useState(userObj?.bio || "");
  const [livesAt, setLivesAt] = useState(userObj?.livesAt || "");
  const [country, setCountry] = useState(userObj?.country || "");
  const [workplace, setWorkplace] = useState(userObj?.workplace || "");
  const [gender, setGender] = useState(userObj?.gender || "Không tiết lộ");
  const [isPrivate, setIsPrivate] = useState(userObj?.isPrivate || false);
  const [showNsfw, setShowNsfw] = useState(userObj?.showNsfw || false);

  // Avatar & Cover URL states
  const [avatarUrl, setAvatarUrl] = useState(userObj?.avatarFilename || "");
  const [coverUrl, setCoverUrl] = useState(userObj?.coverFilename || "");
  const [uploading, setUploading] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);

  // Name request states
  const [firstName, setFirstName] = useState("");
  const [middleName, setMiddleName] = useState("");
  const [lastName, setLastName] = useState("");
  const [nameDisplayOrder, setNameDisplayOrder] = useState("last_middle_first");

  // Age states
  const [idProof, setIdProof] = useState("");
  const [dob, setDob] = useState("");

  // Password states
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  // Upload avatar R2
  const handleAvatarUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);
    setError("");
    setMessage("");
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
        const saveRes = await fetch("/api/users/settings", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "profile", avatarFilename: data.url }),
        });
        if (saveRes.ok) {
          setMessage("Cập nhật ảnh đại diện thành công!");
          router.refresh();
        } else {
          setError("Lưu ảnh đại diện thất bại.");
        }
      } else {
        setError("Lỗi tải ảnh lên Cloudflare R2.");
      }
    } catch (err) {
      setError("Không thể kết nối máy chủ.");
    } finally {
      setUploading(false);
    }
  };

  // Upload cover R2
  const handleCoverUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadingCover(true);
    setError("");
    setMessage("");
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
        const saveRes = await fetch("/api/users/settings", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "profile", coverFilename: data.url }),
        });
        if (saveRes.ok) {
          setMessage("Cập nhật ảnh bìa thành công!");
          router.refresh();
        } else {
          setError("Lưu ảnh bìa thất bại.");
        }
      } else {
        setError("Lỗi tải ảnh bìa lên Cloudflare R2.");
      }
    } catch (err) {
      setError("Không thể kết nối máy chủ.");
    } finally {
      setUploadingCover(false);
    }
  };

  // Handle Follow/Unfollow button click
  const handleFollowToggle = async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/users/${targetUsername}/follow`, {
        method: "POST",
      });
      if (res.ok) {
        const data = await res.json();
        setFollowing(data.following);
        router.refresh();
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  // Submit Settings
  const handleSaveSettings = async (action: string) => {
    setMessage("");
    setError("");
    setLoading(true);

    let payload: any = { action };

    if (action === "profile") {
      payload = { ...payload, fullName, bio, livesAt, country, workplace, gender, isPrivate, showNsfw };
    } else if (action === "name_request") {
      payload = { ...payload, firstName, middleName, lastName, nameDisplayOrder };
    } else if (action === "age_verification") {
      payload = { ...payload, idProofFilename: idProof, dob };
    } else if (action === "password") {
      payload = { ...payload, currentPassword, newPassword };
    }

    try {
      const res = await fetch("/api/users/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (res.ok) {
        setMessage(data.message);
        // Reset password fields
        setCurrentPassword("");
        setNewPassword("");
        router.refresh();
      } else {
        setError(data.error);
      }
    } catch (e) {
      setError("Có lỗi hệ thống xảy ra.");
    } finally {
      setLoading(false);
    }
  };

  if (!isSetting) {
    return (
      <button
        onClick={handleFollowToggle}
        disabled={loading}
        className={`px-5 py-2 rounded-xl text-xs font-bold shadow-premium transition-all active:scale-95 ${
          following
            ? "bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-200"
            : "bg-primary hover:bg-primary-hover text-white"
        }`}
      >
        {loading ? "Đang xử lý..." : following ? "Đang theo dõi" : "Theo dõi"}
      </button>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
      {/* Sidebar navigation tabs for Own Profile Settings */}
      {isOwnProfile && (
        <div className="md:col-span-1 bg-[var(--card-bg)] border border-[var(--card-border)] p-3 rounded-2xl h-fit space-y-1">
          <button
            onClick={() => setActiveTab("posts")}
            className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-xs text-left transition-all ${
              activeTab === "posts"
                ? "bg-primary text-white shadow-premium"
                : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            <User className="w-4.5 h-4.5" />
            <span>Bài viết của bạn</span>
          </button>
          <button
            onClick={() => setActiveTab("info")}
            className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-xs text-left transition-all ${
              activeTab === "info"
                ? "bg-primary text-white shadow-premium"
                : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            <Settings className="w-4.5 h-4.5" />
            <span>Thông tin cá nhân</span>
          </button>
          <button
            onClick={() => setActiveTab("name")}
            className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-xs text-left transition-all ${
              activeTab === "name"
                ? "bg-primary text-white shadow-premium"
                : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            <User className="w-4.5 h-4.5" />
            <span>Yêu cầu đổi tên</span>
          </button>
          <button
            onClick={() => setActiveTab("age")}
            className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-xs text-left transition-all ${
              activeTab === "age"
                ? "bg-primary text-white shadow-premium"
                : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            <ShieldAlert className="w-4.5 h-4.5" />
            <span>Xác minh 18+</span>
          </button>
          <button
            onClick={() => setActiveTab("password")}
            className={`w-full flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-xs text-left transition-all ${
              activeTab === "password"
                ? "bg-primary text-white shadow-premium"
                : "text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            <Lock className="w-4.5 h-4.5" />
            <span>Đổi mật khẩu</span>
          </button>
        </div>
      )}

      {/* Tab Panels */}
      <div className={`${isOwnProfile ? "md:col-span-3" : "w-full md:col-span-4"}`}>
        {/* Message Notifications */}
        {message && (
          <div className="mb-4 p-3 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-900/30 text-accent-green text-xs font-semibold rounded-2xl">
            {message}
          </div>
        )}
        {error && (
          <div className="mb-4 p-3 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold rounded-2xl">
            {error}
          </div>
        )}

        {/* 1. Feed posts tab */}
        {activeTab === "posts" && children}

        {/* 2. Basic Info Settings Tab */}
        {activeTab === "info" && (
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium space-y-4">
            <h3 className="font-extrabold text-sm mb-4 border-b border-[var(--card-border)] pb-2.5">
              Cập nhật thông tin cá nhân
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Họ và tên</label>
                <input
                  type="text"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Giới tính</label>
                <select
                  value={gender}
                  onChange={(e) => setGender(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                >
                  <option value="Nam">Nam</option>
                  <option value="Nữ">Nữ</option>
                  <option value="Không tiết lộ">Không tiết lộ</option>
                </select>
              </div>

              <div className="space-y-1 md:col-span-2">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tiểu sử (Bio)</label>
                <textarea
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  rows={2}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary resize-none font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nơi sống</label>
                <input
                  type="text"
                  value={livesAt}
                  onChange={(e) => setLivesAt(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quốc gia</label>
                <input
                  type="text"
                  value={country}
                  onChange={(e) => setCountry(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nơi làm việc</label>
                <input
                  type="text"
                  value={workplace}
                  onChange={(e) => setWorkplace(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1.5 md:col-span-2 border-t border-[var(--card-border)] pt-4 mt-2">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 block">
                  Ảnh đại diện và Ảnh bìa
                </label>
                
                {/* Cover Photo */}
                <div 
                  className="h-32 w-full rounded-2xl bg-gradient-to-r from-primary/30 via-accent-purple/20 to-accent-pink/30 relative overflow-hidden group cursor-pointer border border-[var(--card-border)]"
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
                    <Camera className="w-4 h-4" />
                    <span className="text-xs font-bold">Thay đổi ảnh bìa</span>
                  </div>
                  {uploadingCover && (
                    <div className="absolute inset-0 bg-black/60 flex items-center justify-center text-primary">
                      <Loader2 className="w-5 h-5 animate-spin" />
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

                {/* Avatar Photo */}
                <div className="flex items-end gap-4 px-4 -mt-8 relative z-10">
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
                      className="w-16 h-16 rounded-2xl object-cover border-4 border-[var(--card-bg)] shadow-md ring-2 ring-gray-100/10"
                      onError={(e) => {
                        e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                      }}
                    />
                    <div className="absolute inset-0 bg-black/40 rounded-2xl flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity">
                      <Camera className="w-4 h-4" />
                    </div>
                    {uploading && (
                      <div className="absolute inset-0 bg-black/60 rounded-2xl flex items-center justify-center text-primary">
                        <Loader2 className="w-4 h-4 animate-spin" />
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
                  <div className="mb-1">
                    <p className="text-[9px] text-gray-400 font-bold uppercase tracking-wider">
                      {uploading ? "Đang tải ảnh đại diện..." : uploadingCover ? "Đang tải ảnh bìa..." : "Nhấp vào ảnh để thay đổi"}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Checkboxes settings */}
            <div className="border-t border-[var(--card-border)] pt-4 mt-2 space-y-3">
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={isPrivate}
                  onChange={(e) => setIsPrivate(e.target.checked)}
                  className="w-4 h-4 text-primary focus:ring-0 rounded"
                />
                <div className="text-xs">
                  <p className="font-bold">Tài khoản riêng tư</p>
                  <p className="text-[10px] text-gray-400">Chỉ những người được bạn phê duyệt mới xem được bài viết.</p>
                </div>
              </label>

              {userObj?.isAdult && (
                <label className="flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={showNsfw}
                    onChange={(e) => setShowNsfw(e.target.checked)}
                    className="w-4 h-4 text-primary focus:ring-0 rounded"
                  />
                  <div className="text-xs">
                    <p className="font-bold">Hiển thị nội dung 18+ (NSFW)</p>
                    <p className="text-[10px] text-gray-400">Cho phép hiển thị bài đăng được đánh dấu nhạy cảm.</p>
                  </div>
                </label>
              )}
            </div>

            <button
              onClick={() => handleSaveSettings("profile")}
              disabled={loading}
              className="mt-4 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-premium flex items-center gap-1.5 active:scale-95 disabled:opacity-50 transition-all"
            >
              <Save className="w-4 h-4" />
              Lưu thay đổi
            </button>
          </div>
        )}

        {/* 3. Name Request Tab */}
        {activeTab === "name" && (
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium space-y-4">
            <h3 className="font-extrabold text-sm mb-2.5 border-b border-[var(--card-border)] pb-2.5">
              Gửi yêu cầu thay đổi tên hiển thị
            </h3>
            <p className="text-[11px] text-gray-400 leading-normal">
              ⚠️ Để tránh giả mạo danh tính, việc đổi tên có họ & tên đệm đầy đủ (hơn 2 từ) bắt buộc phải được Admin xem xét và phê duyệt trước khi cập nhật.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Họ</label>
                <input
                  type="text"
                  placeholder="Nguyễn"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tên đệm</label>
                <input
                  type="text"
                  placeholder="Hoàng"
                  value={middleName}
                  onChange={(e) => setMiddleName(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tên</label>
                <input
                  type="text"
                  placeholder="Dũng"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>
            </div>

            <div className="space-y-1 mt-2">
              <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Thứ tự hiển thị</label>
              <select
                value={nameDisplayOrder}
                onChange={(e) => setNameDisplayOrder(e.target.value)}
                className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
              >
                <option value="last_middle_first">Họ + Tên đệm + Tên (Kiểu Việt Nam - Nguyễn Hoàng Dũng)</option>
                <option value="first_middle_last">Tên + Tên đệm + Họ (Kiểu phương Tây - Dũng Hoàng Nguyễn)</option>
              </select>
            </div>

            <div className="flex items-center gap-2 p-3 bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-900/30 text-accent-orange text-[10.5px] font-semibold rounded-2xl">
              Trạng thái yêu cầu hiện tại: {userObj?.nameChangeStatus === "pending" ? "Đang chờ duyệt" : "Không có yêu cầu chờ"}
            </div>

            <button
              onClick={() => handleSaveSettings("name_request")}
              disabled={loading}
              className="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-premium active:scale-95 disabled:opacity-50 transition-all"
            >
              Gửi yêu cầu đổi tên
            </button>
          </div>
        )}

        {/* 4. Age Verification Tab */}
        {activeTab === "age" && (
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium space-y-4">
            <h3 className="font-extrabold text-sm mb-2.5 border-b border-[var(--card-border)] pb-2.5">
              Xác thực độ tuổi 18+
            </h3>
            <p className="text-[11px] text-gray-400 leading-normal">
              Đăng tải tài liệu chứng minh nhân dân hoặc hộ chiếu của bạn để xác minh bạn trên 18 tuổi. Việc này cần thiết để mở khóa xem các hình ảnh/video nhạy cảm (NSFW) trên Frest.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Ngày sinh</label>
                <input
                  type="date"
                  value={dob}
                  onChange={(e) => setDob(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Đường dẫn ảnh chụp CMND từ R2</label>
                <input
                  type="text"
                  placeholder="Ví dụ: proof_hoangdung.jpg"
                  value={idProof}
                  onChange={(e) => setIdProof(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>
            </div>

            <div className="flex items-center gap-2 p-3 bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-900/30 text-accent-orange text-[10.5px] font-semibold rounded-2xl">
              Trạng thái xác minh: {userObj?.ageVerificationStatus === "pending" ? "Đang chờ admin duyệt" : userObj?.ageVerificationStatus === "verified" ? "Đã xác minh thành công 18+" : "Chưa xác minh"}
            </div>

            <button
              onClick={() => handleSaveSettings("age_verification")}
              disabled={loading}
              className="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-premium active:scale-95 disabled:opacity-50 transition-all"
            >
              Gửi yêu cầu xác thực tuổi
            </button>
          </div>
        )}

        {/* 5. Password Tab */}
        {activeTab === "password" && (
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium space-y-4">
            <h3 className="font-extrabold text-sm mb-4 border-b border-[var(--card-border)] pb-2.5">
              Đổi mật khẩu
            </h3>

            <div className="space-y-3 max-w-sm">
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mật khẩu hiện tại</label>
                <input
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mật khẩu mới</label>
                <input
                  type="password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full px-4 py-2.5 rounded-xl border border-[var(--card-border)] bg-gray-50 dark:bg-[#18181c] text-xs focus:outline-none focus:border-primary font-semibold"
                />
              </div>
            </div>

            <button
              onClick={() => handleSaveSettings("password")}
              disabled={loading}
              className="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-premium flex items-center gap-1.5 active:scale-95 disabled:opacity-50 transition-all"
            >
              <KeyRound className="w-4 h-4" />
              Đổi mật khẩu
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
