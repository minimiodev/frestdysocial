"use client";

import { useState, useEffect } from "react";
import { Check, X, ShieldAlert, User, MessageSquare, AlertTriangle, FileText, Settings, Award, Users, FileSignature, Trash2 } from "lucide-react";

type TabType = "names" | "ages" | "reports" | "complaints" | "users" | "pages" | "posts" | "settings";

export default function AdminDashboard() {
  const [isAdmin, setIsAdmin] = useState(false);
  const [data, setData] = useState<any>({});
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<TabType>("names");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  // State cho việc sửa đổi cài đặt hệ thống (Settings)
  const [newSettingKey, setNewSettingKey] = useState("");
  const [newSettingVal, setNewSettingVal] = useState("");

  // 1. Kiểm tra quyền admin & Tải dữ liệu mặc định ban đầu
  useEffect(() => {
    fetch("/api/admin?type=all")
      .then((res) => {
        if (res.ok) {
          setIsAdmin(true);
          return res.json();
        }
        throw new Error("Không có quyền truy cập Admin.");
      })
      .then((resData) => {
        setData(resData);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, []);

  // 2. Hàm tải lại dữ liệu cho tab hiện tại
  const refreshTab = async (tab: TabType) => {
    try {
      const res = await fetch(`/api/admin?type=${tab}`);
      if (res.ok) {
        const tabData = await res.json();
        setData((prev: any) => ({ ...prev, ...tabData }));
      }
    } catch (e) {
      console.error(`Lỗi tải lại dữ liệu tab ${tab}:`, e);
    }
  };

  // 3. Xử lý các hành động cơ bản (tên, tuổi, báo cáo)
  const handleAction = async (actionType: string, targetUserId: number | null, targetId: number | null, approve: boolean) => {
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ actionType, targetUserId, targetId, approve }),
      });

      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab(activeTab);
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Có lỗi hệ thống xảy ra.");
    }
  };

  // 4. Các hàm thao tác nâng cao trong Admin
  const handleUpdateUserVerification = async (userId: number, value: string) => {
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "update_user_verification",
          targetUserId: userId,
          verificationType: value === "none" ? null : value,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("users");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi cập nhật tích xác minh người dùng.");
    }
  };

  const handleUpdateUserStatus = async (userId: number, value: string) => {
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "update_user_status",
          targetUserId: userId,
          status: value,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("users");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi cập nhật trạng thái người dùng.");
    }
  };

  const handleUpdatePageVerification = async (pageId: number, isVerified: boolean) => {
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "update_page_verification",
          targetId: pageId,
          isVerified,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("pages");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi cập nhật tích xác minh Fanpage.");
    }
  };

  const handleDeletePage = async (pageId: number) => {
    if (!confirm("Bạn có chắc chắn muốn xóa trang Fanpage này vĩnh viễn không?")) return;
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "delete_page",
          targetId: pageId,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("pages");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi khi xóa trang Fanpage.");
    }
  };

  const handleDeletePost = async (postId: number) => {
    if (!confirm("Bạn có chắc chắn muốn xóa bài đăng này không?")) return;
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "delete_post",
          targetId: postId,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("posts");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi khi xóa bài đăng.");
    }
  };

  const handleUpdateSetting = async (key: string, value: string) => {
    setError("");
    setSuccess("");
    try {
      const res = await fetch("/api/admin", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          actionType: "update_setting",
          keyName: key,
          keyValue: value,
        }),
      });
      const resData = await res.json();
      if (res.ok) {
        setSuccess(resData.message);
        refreshTab("settings");
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Lỗi cập nhật cấu hình hệ thống.");
    }
  };

  const handleAddSetting = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSettingKey.trim()) return;
    await handleUpdateSetting(newSettingKey.trim(), newSettingVal);
    setNewSettingKey("");
    setNewSettingVal("");
  };

  // 5. Đăng xuất Admin
  const handleAdminLogout = async () => {
    try {
      const res = await fetch("/api/admin/logout", {
        method: "POST",
      });
      if (res.ok) {
        window.location.href = "/admin/login";
      }
    } catch (e) {
      console.error("Lỗi đăng xuất admin:", e);
    }
  };

  // 6. Theo dõi khi đổi Tab
  const handleTabChange = (tab: TabType) => {
    setActiveTab(tab);
    refreshTab(tab);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  if (!isAdmin) {
    return (
      <div className="max-w-md mx-auto mt-20 text-center space-y-4">
        <div className="w-16 h-16 rounded-3xl bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink flex items-center justify-center mx-auto">
          <ShieldAlert className="w-8 h-8" />
        </div>
        <h3 className="font-extrabold text-lg text-gray-800 dark:text-gray-200">Không có quyền truy cập</h3>
        <p className="text-xs text-gray-400 leading-normal">
          Bạn cần đăng nhập bằng tài khoản Quản trị viên (Admin) để sử dụng trang này.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header Dashboard */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold flex items-center gap-2">
            <ShieldAlert className="w-6 h-6 text-primary" />
            Hệ thống Quản trị Frest
          </h2>
          <p className="text-xs text-gray-400 font-medium mt-1">
            Bảng điều khiển toàn diện: Cấp tích xanh xác minh, quản lý thành viên, kiểm duyệt bài viết và quản lý website.
          </p>
        </div>
        <button
          onClick={handleAdminLogout}
          className="px-4 py-2 bg-red-500 hover:bg-red-600 active:scale-95 text-white rounded-xl text-xs font-bold transition-all shadow-md shrink-0 self-start sm:self-auto"
        >
          Đăng xuất Admin
        </button>
      </div>

      {/* Tabs Navigation */}
      <div className="flex gap-2 overflow-x-auto pb-1.5 scrollbar-thin">
        {[
          { id: "names", label: "Duyệt đổi tên" },
          { id: "ages", label: "Duyệt độ tuổi 18+" },
          { id: "reports", label: "Báo cáo vi phạm" },
          { id: "complaints", label: "Khiếu nại bản quyền" },
          { id: "users", label: "👥 Thành viên" },
          { id: "pages", label: "📄 Fanpage" },
          { id: "posts", label: "📝 Bài viết" },
          { id: "settings", label: "⚙ Cài đặt hệ thống" }
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => handleTabChange(tab.id as TabType)}
            className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-colors shrink-0 ${
              activeTab === tab.id
                ? "bg-primary text-white shadow-premium"
                : "bg-[var(--card-bg)] border border-[var(--card-border)] text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Notifications */}
      {success && (
        <div className="p-3.5 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-900/30 text-accent-green text-xs font-semibold rounded-2xl">
          ✓ {success}
        </div>
      )}
      {error && (
        <div className="p-3.5 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 text-accent-pink text-xs font-semibold rounded-2xl">
          ⚠ {error}
        </div>
      )}

      {/* Main Content Area */}
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] p-6 rounded-3xl shadow-premium">
        
        {/* 1. Name Change Requests */}
        {activeTab === "names" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm mb-4">Danh sách yêu cầu đổi tên</h3>
            {data.nameRequests && data.nameRequests.length > 0 ? (
              <div className="space-y-3">
                {data.nameRequests.map((req: any) => (
                  <div key={req.id} className="flex justify-between items-center p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl">
                    <div className="space-y-1">
                      <p className="text-xs font-bold">@{req.username}</p>
                      <div className="text-xs flex gap-2">
                        <span className="text-gray-400">Hiện tại:</span>
                        <span className="font-semibold text-gray-600 dark:text-gray-300">{req.fullName}</span>
                      </div>
                      <div className="text-xs flex gap-2">
                        <span className="text-primary font-bold">Yêu cầu mới:</span>
                        <span className="font-bold text-primary">
                          {req.pendingLastName} {req.pendingMiddleName} {req.pendingFirstName}
                        </span>
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleAction("name_approve", req.id, null, true)}
                        className="w-8 h-8 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary-hover active:scale-95 transition-all shadow-md"
                        title="Phê duyệt"
                      >
                        <Check className="w-4.5 h-4.5" />
                      </button>
                      <button
                        onClick={() => handleAction("name_approve", req.id, null, false)}
                        className="w-8 h-8 rounded-xl bg-accent-pink text-white flex items-center justify-center hover:bg-red-600 active:scale-95 transition-all shadow-md"
                        title="Từ chối"
                      >
                        <X className="w-4.5 h-4.5" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 py-6">Không có yêu cầu đổi tên nào chờ xử lý.</p>
            )}
          </div>
        )}

        {/* 2. Age Verification Requests */}
        {activeTab === "ages" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm mb-4">Danh sách yêu cầu xác minh độ tuổi (18+)</h3>
            {data.ageRequests && data.ageRequests.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {data.ageRequests.map((req: any) => (
                  <div key={req.id} className="p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl flex flex-col justify-between gap-4">
                    <div className="space-y-2">
                      <p className="text-xs font-bold">@{req.username} ({req.fullName})</p>
                      <p className="text-[11px] text-gray-400">
                        Ngày sinh: {new Date(req.dob).toLocaleDateString("vi-VN")}
                      </p>
                      {req.idProofFilename && (
                        <div className="rounded-xl overflow-hidden border border-[var(--card-border)] bg-black/5 dark:bg-white/5">
                          <img
                            src={`/uploads/proofs/${req.idProofFilename}`}
                            alt="CMND/Passport Proof"
                            className="w-full h-32 object-cover"
                          />
                        </div>
                      )}
                    </div>
                    <div className="flex gap-2 mt-2">
                      <button
                        onClick={() => handleAction("age_approve", req.id, null, true)}
                        className="flex-1 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary-hover active:scale-95 transition-all flex items-center justify-center gap-1 shadow-md"
                      >
                        <Check className="w-4 h-4" /> Phê duyệt
                      </button>
                      <button
                        onClick={() => handleAction("age_approve", req.id, null, false)}
                        className="flex-1 py-2 rounded-xl bg-accent-pink text-white text-xs font-bold hover:bg-red-600 active:scale-95 transition-all flex items-center justify-center gap-1 shadow-md"
                      >
                        <X className="w-4 h-4" /> Từ chối
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 py-6">Không có yêu cầu xác minh tuổi nào chờ xử lý.</p>
            )}
          </div>
        )}

        {/* 3. Reports */}
        {activeTab === "reports" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm mb-4">Danh sách báo cáo vi phạm</h3>
            {data.reports && data.reports.length > 0 ? (
              <div className="space-y-3">
                {data.reports.map((req: any) => (
                  <div key={req.id} className="p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl space-y-2">
                    <div className="flex justify-between items-start">
                      <div className="space-y-1">
                        <p className="text-xs font-bold text-gray-800 dark:text-gray-200">
                          Người báo cáo: @{req.reporter.username}
                        </p>
                        {req.reportedUser && (
                          <p className="text-xs text-accent-pink font-semibold">
                            Đối tượng bị báo cáo: User @{req.reportedUser.username}
                          </p>
                        )}
                        {req.reportedPost && (
                          <p className="text-xs text-accent-orange font-semibold">
                            Bài viết bị báo cáo (ID: {req.reportedPost.id}): &ldquo;{req.reportedPost.content}&rdquo;
                          </p>
                        )}
                        <p className="text-[11px] text-gray-400">Lý do: {req.reason}</p>
                        {req.details && <p className="text-[11px] text-gray-400 italic">Chi tiết: {req.details}</p>}
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => handleAction("report_resolve", null, req.id, true)}
                          className="w-8 h-8 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary-hover active:scale-95 transition-all shadow-md"
                          title="Đồng ý & Xử lý"
                        >
                          <Check className="w-4.5 h-4.5" />
                        </button>
                        <button
                          onClick={() => handleAction("report_resolve", null, req.id, false)}
                          className="w-8 h-8 rounded-xl bg-gray-200 dark:bg-gray-800 text-gray-500 flex items-center justify-center hover:bg-gray-300 dark:hover:bg-gray-700 active:scale-95 transition-all shadow-md"
                          title="Bác bỏ báo cáo"
                        >
                          <X className="w-4.5 h-4.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 py-6">Không có báo cáo vi phạm nào chờ xử lý.</p>
            )}
          </div>
        )}

        {/* 4. Copyright Complaints */}
        {activeTab === "complaints" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm mb-4">Danh sách khiếu nại bản quyền</h3>
            {data.complaints && data.complaints.length > 0 ? (
              <div className="space-y-3">
                {data.complaints.map((req: any) => (
                  <div key={req.id} className="p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl space-y-2">
                    <div className="space-y-1">
                      <p className="text-xs font-bold text-gray-800 dark:text-gray-200">
                        Bên khiếu nại: {req.reporterName} ({req.reporterEmail})
                      </p>
                      <p className="text-xs font-bold text-accent-pink">
                        URL bị khiếu nại: <a href={req.postUrl} target="_blank" rel="noreferrer" className="underline">{req.postUrl}</a>
                      </p>
                      <p className="text-[11px] text-gray-400 leading-normal">Mô tả tác phẩm gốc: {req.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 py-6">Không có khiếu nại bản quyền nào chờ xử lý.</p>
            )}
          </div>
        )}

        {/* 5. Users Management (New) */}
        {activeTab === "users" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-extrabold text-sm">Quản lý Thành viên ({data.users?.length || 0})</h3>
            </div>
            <div className="overflow-x-auto border border-[var(--card-border)] rounded-2xl">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-gray-50 dark:bg-[#18181c] text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-[var(--card-border)]">
                    <th className="p-3">Thành viên</th>
                    <th className="p-3">Email / SĐT</th>
                    <th className="p-3">Huy hiệu Tích</th>
                    <th className="p-3">Trạng thái</th>
                    <th className="p-3">Ngày đăng ký</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--card-border)] text-xs">
                  {data.users && data.users.length > 0 ? (
                    data.users.map((u: any) => (
                      <tr key={u.id} className="hover:bg-gray-50/50 dark:hover:bg-[#1a1a1e]/50">
                        <td className="p-3 flex items-center gap-2">
                          <img
                            src={u.avatarFilename ? `/uploads/avatars/${u.avatarFilename}` : "/uploads/avatars/avatar_default.png"}
                            alt={u.username}
                            className="w-8 h-8 rounded-full object-cover border border-[var(--card-border)]"
                            onError={(e: any) => {
                              e.target.src = "/uploads/avatars/avatar_default.png";
                            }}
                          />
                          <div>
                            <p className="font-bold text-gray-800 dark:text-gray-100">{u.fullName}</p>
                            <p className="text-[11px] text-gray-400">@{u.username}</p>
                          </div>
                        </td>
                        <td className="p-3">
                          <p>{u.email}</p>
                          {u.phoneNumber && <p className="text-[10px] text-gray-400">{u.phoneNumber}</p>}
                        </td>
                        <td className="p-3">
                          <select
                            value={u.verificationType || "none"}
                            onChange={(e) => handleUpdateUserVerification(u.id, e.target.value)}
                            className="px-2 py-1 bg-white dark:bg-[#1f1f23] border border-gray-300 dark:border-gray-700 rounded-lg text-xs focus:outline-none text-gray-800 dark:text-gray-100"
                          >
                            <option value="none">Không có tích</option>
                            <option value="official">Tích xanh dương (Official)</option>
                            <option value="subscribed">Tích vàng (Subscribed)</option>
                            <option value="developer">⚙ Dev (Nhà phát triển)</option>
                            <option value="business">💼 Biz (Doanh nghiệp)</option>
                          </select>
                        </td>
                        <td className="p-3">
                          <select
                            value={u.status}
                            onChange={(e) => handleUpdateUserStatus(u.id, e.target.value)}
                            className={`px-2.5 py-1 rounded-lg text-xs font-bold focus:outline-none border ${
                              u.status === "active"
                                ? "bg-green-500/10 text-green-500 border-green-500/20"
                                : "bg-red-500/10 text-red-500 border-red-500/20"
                            }`}
                          >
                            <option value="active" className="text-green-500">Hoạt động (Active)</option>
                            <option value="suspended" className="text-red-500">Bị khóa (Suspended)</option>
                          </select>
                        </td>
                        <td className="p-3 text-[11px] text-gray-400">
                          {new Date(u.createdAt).toLocaleDateString("vi-VN")}
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="p-6 text-center text-gray-400">
                        Không có dữ liệu thành viên.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* 6. Pages Management (New) */}
        {activeTab === "pages" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm">Quản lý Fanpage ({data.pages?.length || 0})</h3>
            <div className="overflow-x-auto border border-[var(--card-border)] rounded-2xl">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-gray-50 dark:bg-[#18181c] text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-[var(--card-border)]">
                    <th className="p-3">Trang Fanpage</th>
                    <th className="p-3">Danh mục</th>
                    <th className="p-3">Chủ sở hữu</th>
                    <th className="p-3">Tích xanh</th>
                    <th className="p-3">Ngày tạo</th>
                    <th className="p-3">Hành động</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--card-border)] text-xs">
                  {data.pages && data.pages.length > 0 ? (
                    data.pages.map((p: any) => (
                      <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-[#1a1a1e]/50">
                        <td className="p-3 flex items-center gap-2">
                          <img
                            src={p.avatarFilename ? `/uploads/avatars/${p.avatarFilename}` : "/uploads/avatars/avatar_default.png"}
                            alt={p.pageName}
                            className="w-8 h-8 rounded-full object-cover border border-[var(--card-border)]"
                            onError={(e: any) => {
                              e.target.src = "/uploads/avatars/avatar_default.png";
                            }}
                          />
                          <div>
                            <p className="font-bold text-gray-800 dark:text-gray-100">{p.pageName}</p>
                            <p className="text-[11px] text-gray-400">@{p.pageUsername}</p>
                          </div>
                        </td>
                        <td className="p-3">{p.category}</td>
                        <td className="p-3 font-semibold">@{p.owner?.username}</td>
                        <td className="p-3">
                          <label className="relative inline-flex items-center cursor-pointer">
                            <input
                              type="checkbox"
                              checked={p.isVerified}
                              onChange={(e) => handleUpdatePageVerification(p.id, e.target.checked)}
                              className="sr-only peer"
                            />
                            <div className="w-9 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1877f2]" />
                          </label>
                        </td>
                        <td className="p-3 text-[11px] text-gray-400">
                          {new Date(p.createdAt).toLocaleDateString("vi-VN")}
                        </td>
                        <td className="p-3">
                          <button
                            onClick={() => handleDeletePage(p.id)}
                            className="p-1.5 text-accent-pink hover:bg-red-50 dark:hover:bg-red-950/20 rounded-lg transition-colors"
                            title="Xóa Fanpage"
                          >
                            <Trash2 className="w-4.5 h-4.5" />
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={6} className="p-6 text-center text-gray-400">
                        Không có trang Fanpage nào.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* 7. Posts Management (New) */}
        {activeTab === "posts" && (
          <div className="space-y-4">
            <h3 className="font-extrabold text-sm">Quản lý Bài viết ({data.posts?.length || 0})</h3>
            <div className="overflow-x-auto border border-[var(--card-border)] rounded-2xl">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-gray-50 dark:bg-[#18181c] text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-[var(--card-border)]">
                    <th className="p-3">Người đăng</th>
                    <th className="p-3">Nội dung</th>
                    <th className="p-3">Ảnh đính kèm</th>
                    <th className="p-3">Ngày đăng</th>
                    <th className="p-3">Hành động</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--card-border)] text-xs">
                  {data.posts && data.posts.length > 0 ? (
                    data.posts.map((p: any) => (
                      <tr key={p.id} className="hover:bg-gray-50/50 dark:hover:bg-[#1a1a1e]/50">
                        <td className="p-3 font-semibold">@{p.user?.username}</td>
                        <td className="p-3 max-w-[250px] truncate leading-normal" title={p.content}>
                          {p.content}
                        </td>
                        <td className="p-3">
                          {p.imageFilename ? (
                            <img
                              src={p.imageFilename.startsWith("http") ? p.imageFilename : `/uploads/posts/${p.imageFilename}`}
                              alt="Post Media"
                              className="w-12 h-12 object-cover rounded-lg border border-[var(--card-border)]"
                            />
                          ) : (
                            <span className="text-gray-400 text-[10px]">Không có ảnh</span>
                          )}
                        </td>
                        <td className="p-3 text-[11px] text-gray-400">
                          {new Date(p.createdAt).toLocaleDateString("vi-VN")}
                        </td>
                        <td className="p-3">
                          <button
                            onClick={() => handleDeletePost(p.id)}
                            className="p-1.5 text-accent-pink hover:bg-red-50 dark:hover:bg-red-950/20 rounded-lg transition-colors"
                            title="Xóa bài đăng"
                          >
                            <Trash2 className="w-4.5 h-4.5" />
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={5} className="p-6 text-center text-gray-400">
                        Không có bài viết nào.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* 8. Settings Management (New) */}
        {activeTab === "settings" && (
          <div className="space-y-6">
            <h3 className="font-extrabold text-sm">Cài đặt Cấu hình hệ thống</h3>
            
            {/* Form thêm cấu hình mới */}
            <form onSubmit={handleAddSetting} className="p-4 bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-2xl space-y-3">
              <p className="text-xs font-bold text-gray-600 dark:text-gray-300">Thêm mới / Cập nhật Cài đặt nhanh</p>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <input
                  type="text"
                  required
                  placeholder="Ví dụ: site_name"
                  value={newSettingKey}
                  onChange={(e) => setNewSettingKey(e.target.value)}
                  className="px-3.5 py-2 text-xs bg-white dark:bg-[#1f1f23] border border-gray-300 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-gray-800 dark:text-gray-100 font-medium"
                />
                <input
                  type="text"
                  placeholder="Giá trị cấu hình..."
                  value={newSettingVal}
                  onChange={(e) => setNewSettingVal(e.target.value)}
                  className="px-3.5 py-2 text-xs bg-white dark:bg-[#1f1f23] border border-gray-300 dark:border-gray-700 rounded-xl focus:outline-none focus:border-primary text-gray-800 dark:text-gray-100 font-medium"
                />
              </div>
              <button
                type="submit"
                className="px-4 py-2 bg-primary hover:bg-primary-hover active:scale-95 text-white rounded-xl text-xs font-bold transition-all shadow-md"
              >
                Lưu cấu hình
              </button>
            </form>

            {/* Bảng danh sách các settings hiện tại */}
            <div className="overflow-x-auto border border-[var(--card-border)] rounded-2xl">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-gray-50 dark:bg-[#18181c] text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-[var(--card-border)]">
                    <th className="p-3">Tên cấu hình (Key)</th>
                    <th className="p-3">Giá trị cấu hình (Value)</th>
                    <th className="p-3">Hành động</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--card-border)] text-xs">
                  {data.settings && data.settings.length > 0 ? (
                    data.settings.map((s: any) => (
                      <tr key={s.keyName} className="hover:bg-gray-50/50 dark:hover:bg-[#1a1a1e]/50">
                        <td className="p-3 font-mono font-bold text-gray-700 dark:text-gray-300">{s.keyName}</td>
                        <td className="p-3">
                          <input
                            type="text"
                            defaultValue={s.keyValue || ""}
                            onBlur={(e) => {
                              if (e.target.value !== (s.keyValue || "")) {
                                handleUpdateSetting(s.keyName, e.target.value);
                              }
                            }}
                            className="w-full px-2 py-1 bg-transparent border border-transparent hover:border-gray-300 dark:hover:border-gray-700 focus:border-primary focus:bg-white dark:focus:bg-[#1f1f23] rounded-lg transition-all focus:outline-none text-gray-800 dark:text-gray-100 font-medium"
                            placeholder="(Chưa cấu hình)"
                          />
                        </td>
                        <td className="p-3">
                          <span className="text-[10px] text-gray-400 font-medium">Bấm ra ngoài để tự động lưu</span>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={3} className="p-6 text-center text-gray-400">
                        Không có cài đặt cấu hình nào trong database.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
