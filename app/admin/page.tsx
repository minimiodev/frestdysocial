"use client";

import { useState, useEffect } from "react";
import { Check, X, ShieldAlert, User, MessageSquare, AlertTriangle, FileText } from "lucide-react";

export default function AdminDashboard() {
  const [isAdmin, setIsAdmin] = useState(false);
  const [data, setData] = useState<any>({});
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"names" | "ages" | "reports" | "complaints">("names");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  // 1. Kiểm tra quyền admin & Tải dữ liệu
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
        // Tải lại dữ liệu sau khi duyệt
        const updatedRes = await fetch(`/api/admin?type=${activeTab}`);
        const updatedData = await updatedRes.json();
        setData((prev: any) => ({ ...prev, ...updatedData }));
      } else {
        setError(resData.error);
      }
    } catch (e) {
      setError("Có lỗi hệ thống xảy ra.");
    }
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
      <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl p-6 shadow-premium">
        <h2 className="text-xl font-extrabold flex items-center gap-2">
          <ShieldAlert className="w-6 h-6 text-primary" />
          Hệ thống Quản trị Frest
        </h2>
        <p className="text-xs text-gray-400 font-medium mt-1">Phê duyệt yêu cầu đổi tên, xác minh độ tuổi và xử lý báo cáo vi phạm thành viên.</p>
      </div>

      {/* Tabs navigation */}
      <div className="flex gap-2.5 overflow-x-auto pb-1">
        <button
          onClick={() => setActiveTab("names")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition-colors shrink-0 ${
            activeTab === "names"
              ? "bg-primary text-white shadow-premium"
              : "bg-[var(--card-bg)] border border-[var(--card-border)] text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
          }`}
        >
          Duyệt tên hiển thị
        </button>
        <button
          onClick={() => setActiveTab("ages")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition-colors shrink-0 ${
            activeTab === "ages"
              ? "bg-primary text-white shadow-premium"
              : "bg-[var(--card-bg)] border border-[var(--card-border)] text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
          }`}
        >
          Xác minh độ tuổi (18+)
        </button>
        <button
          onClick={() => setActiveTab("reports")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition-colors shrink-0 ${
            activeTab === "reports"
              ? "bg-primary text-white shadow-premium"
              : "bg-[var(--card-bg)] border border-[var(--card-border)] text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
          }`}
        >
          Báo cáo vi phạm
        </button>
        <button
          onClick={() => setActiveTab("complaints")}
          className={`px-5 py-2.5 rounded-xl text-xs font-bold transition-colors shrink-0 ${
            activeTab === "complaints"
              ? "bg-primary text-white shadow-premium"
              : "bg-[var(--card-bg)] border border-[var(--card-border)] text-gray-500 hover:bg-gray-100 dark:hover:bg-[#202024]"
          }`}
        >
          Khiếu nại bản quyền
        </button>
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

      {/* Tab Panels */}
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
                      {/* Proof Image */}
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
                        URL bị khiếu nại: <a href={req.infringingUrl} target="_blank" rel="noreferrer" className="underline">{req.infringingUrl}</a>
                      </p>
                      <p className="text-[11px] text-gray-400 leading-normal">Mô tả tác phẩm gốc: {req.originalWorkDesc}</p>
                      <p className="text-[10px] text-gray-400">Chữ ký điện tử: {req.signature}</p>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-center text-xs text-gray-400 py-6">Không có khiếu nại bản quyền nào chờ xử lý.</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
