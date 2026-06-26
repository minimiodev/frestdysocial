"use client";

import { useState, useEffect, useRef } from "react";
import { supabase } from "@/lib/supabase";
import { 
  Send, 
  Image, 
  Plus, 
  MoreVertical, 
  MessageSquare, 
  Users, 
  Phone, 
  Video, 
  ArrowLeft,
  X,
  PlusCircle
} from "lucide-react";

export default function ChatPage() {
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [conversations, setConversations] = useState<any[]>([]);
  const [activeChat, setActiveChat] = useState<any>(null); // { id: number, type: "user" | "group", name: string, avatar: string }
  const [messages, setMessages] = useState<any[]>([]);
  const [inputText, setInputText] = useState("");
  const [loading, setLoading] = useState(false);
  
  const chatFileInputRef = useRef<HTMLInputElement>(null);
  const [chatUploading, setChatUploading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // States cho modal tạo nhóm
  const [showCreateGroupModal, setShowCreateGroupModal] = useState(false);
  const [groupName, setGroupName] = useState("");
  const [groupDesc, setGroupDesc] = useState("");
  const [selectedMembers, setSelectedMembers] = useState<number[]>([]);
  const [allSystemUsers, setAllSystemUsers] = useState<any[]>([]);

  // 1. Fetch user data
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
        loadConversations(data.user.id);
      })
      .catch(() => {
        window.location.href = "/login";
      });
  }, []);

  // 2. Tải danh sách hội thoại từ DB
  const loadConversations = async (userId: number) => {
    try {
      // Fetch các groups từ API thực tế
      const groupsRes = await fetch("/api/chat/groups");
      const groupsData = await groupsRes.json();
      
      // Fetch các users thực tế từ API /api/chat/users mới tạo
      const usersRes = await fetch("/api/chat/users");
      const usersData = await usersRes.json();

      const formattedGroups = (groupsData.groups || []).map((g: any) => ({
        id: g.id,
        type: "group",
        name: g.name,
        avatar: g.avatarFilename || "group_default.png",
        lastMessage: g.description || "Nhóm trò chuyện",
      }));

      const dbUsers = usersData.users || [];
      setAllSystemUsers(dbUsers); // Lưu lại để hiển thị trong danh sách chọn thành viên nhóm

      setConversations([...formattedGroups, ...dbUsers]);
    } catch (e) {
      console.error("Load conversations error:", e);
    }
  };

  // 3. Tải lịch sử chat khi chọn hội thoại
  useEffect(() => {
    if (!activeChat) return;

    setLoading(true);
    fetch(`/api/chat/messages?receiverId=${activeChat.id}&receiverType=${activeChat.type}`)
      .then((res) => res.json())
      .then((data) => {
        setMessages(data.messages || []);
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, [activeChat]);

  // 4. Kết nối Supabase Realtime để lắng nghe tin nhắn mới
  useEffect(() => {
    if (!currentUser) return;

    const channel = supabase
      .channel("public:messages")
      .on(
        "postgres_changes",
        { event: "INSERT", schema: "public", table: "messages" },
        (payload) => {
          const newMsg = payload.new;

          if (activeChat) {
            const isFromActiveUser = 
              activeChat.type === "user" && 
              ((newMsg.sender_id === currentUser.id && newMsg.receiver_id === activeChat.id) ||
               (newMsg.sender_id === activeChat.id && newMsg.receiver_id === currentUser.id));

            const isFromActiveGroup =
              activeChat.type === "group" &&
              newMsg.receiver_type === "group" &&
              newMsg.receiver_id === activeChat.id;

            if (isFromActiveUser || isFromActiveGroup) {
              const formattedMsg = {
                id: newMsg.id,
                senderId: newMsg.sender_id,
                receiverId: newMsg.receiver_id,
                receiverType: newMsg.receiver_type,
                messageText: newMsg.message_text,
                mediaFilename: newMsg.media_filename,
                mediaType: newMsg.media_type,
                isRead: newMsg.is_read,
                createdAt: newMsg.created_at,
              };

              setMessages((prev) => {
                // Tránh trùng lặp tin nhắn nếu tin nhắn này đã tồn tại trong danh sách (do API POST trả về đã cập nhật ID thực trước đó)
                const exists = prev.some((m) => m.id === formattedMsg.id);
                if (exists) return prev;

                // Nếu là tin nhắn của chính mình và trong list vẫn còn tin nhắn tạm optimistic
                if (formattedMsg.senderId === currentUser.id) {
                  const withoutTemp = prev.filter(
                    (m) => !(m.isOptimistic && m.messageText === formattedMsg.messageText)
                  );
                  return [...withoutTemp, formattedMsg];
                }

                return [...prev, formattedMsg];
              });
            }
          }
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [currentUser, activeChat]);

  // Tự động cuộn xuống dưới cùng khi có tin nhắn mới
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  // 5. Gửi tin nhắn mới với Optimistic Updates
  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim() || !activeChat) return;

    const text = inputText;
    setInputText("");

    // Tạo tin nhắn tạm thời
    const tempId = `temp-${Date.now()}`;
    const optimisticMsg = {
      id: tempId,
      senderId: currentUser.id,
      receiverId: activeChat.id,
      receiverType: activeChat.type,
      messageText: text,
      mediaFilename: null,
      mediaType: null,
      isRead: false,
      createdAt: new Date().toISOString(),
      isOptimistic: true,
    };

    setMessages((prev) => [...prev, optimisticMsg]);

    try {
      const res = await fetch("/api/chat/messages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          receiverId: activeChat.id,
          receiverType: activeChat.type,
          messageText: text,
        }),
      });

      if (res.ok) {
        const result = await res.json();
        // Thay thế tin nhắn tạm bằng tin nhắn thực tế có ID từ DB
        if (result.data) {
          const dbMsg = {
            id: result.data.id,
            senderId: result.data.senderId,
            receiverId: result.data.receiverId,
            receiverType: result.data.receiverType,
            messageText: result.data.messageText,
            mediaFilename: result.data.mediaFilename,
            mediaType: result.data.mediaType,
            isRead: result.data.isRead,
            createdAt: result.data.createdAt,
          };
          setMessages((prev) => 
            prev.map((m) => (m.id === tempId ? dbMsg : m))
          );
        }
      } else {
        console.error("Gửi tin nhắn thất bại");
        setMessages((prev) => prev.filter((m) => m.id !== tempId));
        alert("Không thể gửi tin nhắn. Vui lòng thử lại.");
        setInputText(text); // Phục hồi text
      }
    } catch (e) {
      console.error(e);
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
      alert("Đã xảy ra lỗi kết nối. Vui lòng thử lại.");
      setInputText(text); // Phục hồi text
    }
  };

  // Upload file trong chat trực tiếp lên R2 kèm Optimistic Updates
  const handleChatFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !activeChat) return;

    setChatUploading(true);

    const isVideo = file.type.startsWith("video");
    const tempId = `temp-media-${Date.now()}`;
    
    // Thêm tin nhắn tạm thông báo đang tải lên
    const optimisticMsg = {
      id: tempId,
      senderId: currentUser.id,
      receiverId: activeChat.id,
      receiverType: activeChat.type,
      messageText: `[Đang tải lên ${isVideo ? "video" : "hình ảnh"}...]`,
      mediaFilename: null,
      mediaType: null,
      isRead: false,
      createdAt: new Date().toISOString(),
      isOptimistic: true,
    };
    
    setMessages((prev) => [...prev, optimisticMsg]);

    const formData = new FormData();
    formData.append("file", file);

    try {
      const res = await fetch("/api/upload", {
        method: "POST",
        body: formData,
      });

      if (res.ok) {
        const data = await res.json();
        
        // Gửi tin nhắn chứa media
        const msgRes = await fetch("/api/chat/messages", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            receiverId: activeChat.id,
            receiverType: activeChat.type,
            messageText: `[Đã gửi một ${isVideo ? "video" : "hình ảnh"}]`,
            mediaFilename: data.url,
            mediaType: isVideo ? "video" : "image",
          }),
        });

        if (msgRes.ok) {
          const result = await msgRes.json();
          if (result.data) {
            const dbMsg = {
              id: result.data.id,
              senderId: result.data.senderId,
              receiverId: result.data.receiverId,
              receiverType: result.data.receiverType,
              messageText: result.data.messageText,
              mediaFilename: result.data.mediaFilename,
              mediaType: result.data.mediaType,
              isRead: result.data.isRead,
              createdAt: result.data.createdAt,
            };
            // Thay thế tin nhắn đang tải lên bằng tin nhắn chứa media thực tế
            setMessages((prev) =>
              prev.map((m) => (m.id === tempId ? dbMsg : m))
            );
          }
        } else {
          setMessages((prev) => prev.filter((m) => m.id !== tempId));
          alert("Gửi tin nhắn chứa tệp thất bại.");
        }
      } else {
        const errData = await res.json();
        setMessages((prev) => prev.filter((m) => m.id !== tempId));
        alert(errData.error || "Lỗi tải tệp tin lên Cloudflare R2.");
      }
    } catch (err) {
      console.error(err);
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
      alert("Không thể kết nối máy chủ để tải tệp.");
    } finally {
      setChatUploading(false);
      // Reset input file để có thể chọn lại cùng 1 file nếu muốn
      if (chatFileInputRef.current) {
        chatFileInputRef.current.value = "";
      }
    }
  };

  // 6. Xử lý click tạo nhóm chat
  const handleCreateGroupSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!groupName.trim()) return;

    try {
      const res = await fetch("/api/chat/groups", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: groupName,
          description: groupDesc,
          memberIds: selectedMembers,
        }),
      });

      if (res.ok) {
        const data = await res.json();
        // Reset form
        setGroupName("");
        setGroupDesc("");
        setSelectedMembers([]);
        setShowCreateGroupModal(false);
        // Refresh conversations
        if (currentUser) {
          loadConversations(currentUser.id);
        }
        // Tự chọn nhóm mới tạo làm chat hoạt động
        setActiveChat({
          id: data.group.id,
          type: "group",
          name: data.group.name,
          avatar: data.group.avatarFilename,
        });
      }
    } catch (e) {
      console.error("Tạo nhóm lỗi:", e);
    }
  };

  const handleSelectMember = (userId: number) => {
    if (selectedMembers.includes(userId)) {
      setSelectedMembers(selectedMembers.filter((id) => id !== userId));
    } else {
      setSelectedMembers([...selectedMembers, userId]);
    }
  };

  return (
    <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl overflow-hidden shadow-premium h-[78vh] flex relative">
      
      {/* 1. Sidebar - Conversations list */}
      <div className={`border-r border-[var(--card-border)] flex flex-col shrink-0 transition-all duration-300 ${
        activeChat ? "hidden md:flex w-80" : "w-full md:w-80"
      }`}>
        <div className="p-4 border-b border-[var(--card-border)] flex items-center justify-between">
          <h2 className="font-extrabold text-lg flex items-center gap-2">
            <MessageSquare className="w-5.5 h-5.5 text-primary" />
            Trò chuyện
          </h2>
          {/* Nút tạo nhóm chat */}
          <button
            onClick={() => setShowCreateGroupModal(true)}
            className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-primary transition-all active:scale-95"
            title="Tạo nhóm chat mới"
          >
            <PlusCircle className="w-5 h-5" />
          </button>
        </div>

        {/* Danh sách các cuộc trò chuyện */}
        <div className="flex-1 overflow-y-auto p-2 space-y-1">
          {conversations.length > 0 ? (
            conversations.map((chat) => (
              <div
                key={`${chat.type}-${chat.id}`}
                onClick={() => setActiveChat(chat)}
                className={`flex items-center gap-3 p-3 rounded-2xl cursor-pointer transition-colors ${
                  activeChat && activeChat.id === chat.id && activeChat.type === chat.type
                    ? "bg-primary/10 text-primary"
                    : "hover:bg-gray-100 dark:hover:bg-[#202024]"
                }`}
              >
                <div className="relative shrink-0">
                  <img
                    src={`/uploads/avatars/${chat.avatar}`}
                    alt={chat.name}
                    className="w-11 h-11 rounded-xl object-cover ring-2 ring-gray-100/50"
                    onError={(e) => {
                      e.currentTarget.src = chat.type === "group" ? "/assets/images/icons/icon-192x192.png" : "/assets/images/icons/icon-192x192.png";
                    }}
                  />
                  <div className="absolute -bottom-1 -right-1 w-3 h-3 bg-accent-green border-2 border-[var(--card-bg)] rounded-full" />
                </div>
                <div className="overflow-hidden flex-1">
                  <div className="flex justify-between items-center mb-0.5">
                    <h4 className="font-bold text-xs truncate text-gray-800 dark:text-gray-200">{chat.name}</h4>
                  </div>
                  <p className="text-[10px] text-gray-400 font-medium truncate uppercase tracking-wider">
                    {chat.type === "group" ? "👥 Nhóm chat" : "👤 Cá nhân"}
                  </p>
                </div>
              </div>
            ))
          ) : (
            <p className="text-center text-xs text-gray-400 font-semibold py-8">Chưa có hội thoại nào.</p>
          )}
        </div>
      </div>

      {/* 2. Main Chat Area */}
      <div className={`flex-1 flex flex-col justify-between bg-gray-50/50 dark:bg-[#131316] transition-all duration-300 ${
        activeChat ? "flex" : "hidden md:flex"
      }`}>
        {activeChat ? (
          <>
            {/* Active chat header */}
            <div className="px-5 py-3 bg-[var(--card-bg)] border-b border-[var(--card-border)] flex items-center justify-between z-10">
              <div className="flex items-center gap-3 overflow-hidden">
                {/* Nút quay lại dành cho mobile */}
                <button
                  onClick={() => setActiveChat(null)}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 block md:hidden transition-colors mr-1"
                  title="Quay lại"
                >
                  <ArrowLeft className="w-5 h-5 text-gray-600 dark:text-gray-300" />
                </button>

                <img
                  src={`/uploads/avatars/${activeChat.avatar}`}
                  alt={activeChat.name}
                  className="w-10 h-10 rounded-xl object-cover"
                  onError={(e) => {
                    e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                  }}
                />
                <div className="overflow-hidden">
                  <h3 className="font-extrabold text-sm truncate text-gray-800 dark:text-gray-200">{activeChat.name}</h3>
                  <span className="text-[9.5px] text-accent-green font-bold flex items-center gap-1">
                    <span className="w-1.5 h-1.5 rounded-full bg-accent-green animate-pulse" />
                    Hoạt động
                  </span>
                </div>
              </div>

              <div className="flex gap-1 shrink-0">
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 hidden sm:block"><Phone className="w-4.5 h-4.5" /></button>
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 hidden sm:block"><Video className="w-4.5 h-4.5" /></button>
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400"><MoreVertical className="w-4.5 h-4.5" /></button>
              </div>
            </div>

            {/* Messages box */}
            <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
              {loading ? (
                <div className="flex items-center justify-center h-full">
                  <div className="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin" />
                </div>
              ) : messages.length > 0 ? (
                messages.map((msg) => {
                  const isMine = msg.senderId === currentUser.id;
                  return (
                    <div key={msg.id} className={`flex gap-3 max-w-[85%] sm:max-w-[70%] ${isMine ? "ml-auto flex-row-reverse" : ""}`}>
                      {!isMine && (
                        <img
                          src={`/uploads/avatars/${activeChat.avatar}`}
                          alt="Sender avatar"
                          className="w-8 h-8 rounded-lg object-cover shrink-0 ring-1 ring-gray-100"
                          onError={(e) => {
                            e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                          }}
                        />
                      )}
                      <div className="space-y-1">
                        <div className={`p-3 rounded-2xl text-xs font-medium leading-relaxed overflow-hidden ${
                          isMine
                            ? "bg-primary text-white rounded-tr-none shadow-sm"
                            : "bg-[var(--card-bg)] text-gray-800 dark:text-gray-200 border border-[var(--card-border)] rounded-tl-none"
                        }`}>
                          {msg.mediaFilename ? (
                            msg.mediaType === "video" ? (
                              <video src={msg.mediaFilename} controls className="max-w-[200px] sm:max-w-[280px] rounded-xl" />
                            ) : (
                              <img src={msg.mediaFilename} alt="Media" className="max-w-[200px] sm:max-w-[280px] rounded-xl object-cover" />
                            )
                          ) : (
                            msg.messageText
                          )}
                        </div>
                        <p className={`text-[8px] text-gray-400 font-bold ${isMine ? "text-right" : ""}`}>
                          {new Date(msg.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          {isMine && msg.isRead && " • Đã đọc"}
                        </p>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400 space-y-2">
                  <MessageSquare className="w-8 h-8 opacity-40 text-primary" />
                  <p className="text-xs font-semibold">Chưa có tin nhắn nào. Bắt đầu ngay!</p>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Input Message box */}
            <div className="p-4 bg-[var(--card-bg)] border-t border-[var(--card-border)]">
              <form onSubmit={handleSendMessage} className="flex gap-2 items-center">
                <input
                  type="file"
                  ref={chatFileInputRef}
                  onChange={handleChatFileChange}
                  accept="image/*,video/*"
                  className="hidden"
                />
                
                <button
                  type="button"
                  onClick={() => chatFileInputRef.current?.click()}
                  disabled={chatUploading}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 transition-colors shrink-0 disabled:opacity-50"
                  title="Gửi hình ảnh/video"
                >
                  {chatUploading ? (
                    <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                  ) : (
                    <Image className="w-5 h-5" />
                  )}
                </button>
                <input
                  type="text"
                  placeholder="Nhập tin nhắn..."
                  value={inputText}
                  onChange={(e) => setInputText(e.target.value)}
                  className="flex-1 bg-gray-100 dark:bg-[#18181c] border border-transparent rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-primary focus:bg-white dark:focus:bg-[#1c1c20] transition-colors font-medium"
                />
                <button
                  type="submit"
                  className="w-10 h-10 rounded-xl bg-primary hover:bg-primary-hover text-white flex items-center justify-center shadow-premium active:scale-95 transition-all shrink-0"
                >
                  <Send className="w-4.5 h-4.5" />
                </button>
              </form>
            </div>
          </>
        ) : (
          <div className="flex flex-col items-center justify-center h-full text-gray-400 space-y-3 p-6">
            <div className="w-16 h-16 rounded-3xl bg-primary/10 flex items-center justify-center text-primary">
              <MessageSquare className="w-8 h-8" />
            </div>
            <h3 className="font-extrabold text-sm text-gray-700 dark:text-gray-300">Không có hội thoại nào được chọn</h3>
            <p className="text-[11px] font-medium text-gray-400 max-w-xs text-center leading-normal">
              Vui lòng chọn một người bạn hoặc nhóm chat từ thanh bên trái để bắt đầu nhắn tin thời gian thực.
            </p>
          </div>
        )}
      </div>

      {/* 3. Modal tạo nhóm chat mới */}
      {showCreateGroupModal && (
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-[var(--card-bg)] border border-[var(--card-border)] w-full max-w-md rounded-3xl p-6 shadow-premium space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between">
              <h3 className="font-extrabold text-base flex items-center gap-2">
                <Users className="w-5 h-5 text-primary" />
                Tạo nhóm chat mới
              </h3>
              <button
                onClick={() => setShowCreateGroupModal(false)}
                className="p-1.5 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateGroupSubmit} className="space-y-4">
              {/* Tên nhóm */}
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                  Tên nhóm chat *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Nhập tên nhóm..."
                  value={groupName}
                  onChange={(e) => setGroupName(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary transition-all font-medium"
                />
              </div>

              {/* Mô tả nhóm */}
              <div className="space-y-1">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                  Mô tả (Không bắt buộc)
                </label>
                <input
                  type="text"
                  placeholder="Mô tả mục đích nhóm..."
                  value={groupDesc}
                  onChange={(e) => setGroupDesc(e.target.value)}
                  className="w-full px-3.5 py-2.5 text-xs bg-gray-50 dark:bg-[#18181c] border border-[var(--card-border)] rounded-xl focus:outline-none focus:border-primary transition-all font-medium"
                />
              </div>

              {/* Chọn thành viên */}
              <div className="space-y-2">
                <label className="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                  Chọn thành viên ({selectedMembers.length})
                </label>
                <div className="max-h-36 overflow-y-auto border border-[var(--card-border)] rounded-2xl p-2 space-y-1 bg-gray-50/50 dark:bg-[#18181c]/50">
                  {allSystemUsers.length > 0 ? (
                    allSystemUsers.map((u) => {
                      const isSelected = selectedMembers.includes(u.id);
                      return (
                        <div
                          key={u.id}
                          onClick={() => handleSelectMember(u.id)}
                          className={`flex items-center justify-between p-2 rounded-xl cursor-pointer text-xs transition-colors ${
                            isSelected 
                              ? "bg-primary/10 text-primary font-bold" 
                              : "hover:bg-gray-100 dark:hover:bg-[#202024] text-gray-700 dark:text-gray-300"
                          }`}
                        >
                          <div className="flex items-center gap-2 overflow-hidden">
                            <img
                              src={`/uploads/avatars/${u.avatar}`}
                              alt={u.name}
                              className="w-7 h-7 rounded-lg object-cover"
                              onError={(e) => {
                                e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                              }}
                            />
                            <span className="truncate">{u.name}</span>
                          </div>
                          <input
                            type="checkbox"
                            checked={isSelected}
                            onChange={() => {}} // Handle click bằng parent div
                            className="rounded text-primary focus:ring-primary h-3.5 w-3.5"
                          />
                        </div>
                      );
                    })
                  ) : (
                    <p className="text-center text-[10px] text-gray-400 py-4">Không có thành viên nào.</p>
                  )}
                </div>
              </div>

              {/* Submit buttons */}
              <div className="flex gap-3 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setShowCreateGroupModal(false)}
                  className="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-[#202024] dark:hover:bg-[#2a2a30] text-gray-500 rounded-xl text-xs font-bold transition-all"
                >
                  Hủy bỏ
                </button>
                <button
                  type="submit"
                  disabled={!groupName.trim()}
                  className="px-5 py-2 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold transition-all shadow-md disabled:opacity-50"
                >
                  Tạo nhóm
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
