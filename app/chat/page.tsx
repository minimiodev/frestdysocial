"use client";

import { useState, useEffect, useRef } from "react";
import { supabase } from "@/lib/supabase";
import { Send, Image, Plus, MoreVertical, MessageSquare, Users, Phone, Video } from "lucide-react";

export default function ChatPage() {
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [conversations, setConversations] = useState<any[]>([]);
  const [activeChat, setActiveChat] = useState<any>(null); // { id: number, type: "user" | "group", name: string, avatar: string }
  const [messages, setMessages] = useState<any[]>([]);
  const [inputText, setInputText] = useState("");
  const [loading, setLoading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // 1. Fetch user data
  useEffect(() => {
    fetch("/api/auth/me")
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error();
      })
      .then((data) => {
        setCurrentUser(data.user);
        // Load danh sách bạn bè/group sau khi có user
        loadConversations(data.user.id);
      })
      .catch(() => {
        window.location.href = "/login";
      });
  }, []);

  // 2. Tải danh sách hội thoại
  const loadConversations = async (userId: number) => {
    try {
      // Fetch các groups
      const groupsRes = await fetch("/api/chat/groups");
      const groupsData = await groupsRes.json();
      
      // Fetch danh sách users khác để chat cá nhân (seed data từ database)
      // Để đơn giản, ta mock danh sách hội thoại cá nhân dựa trên user hoạt động
      const mockUsers = [
        { id: 1001, type: "user", name: "Nguyễn Hoàng Dũng", username: "hoangdung", avatar: "avatar_hoangdung.png" },
        { id: 1002, type: "user", name: "Lê Anh Tuấn", username: "anhtuan", avatar: "avatar_anhtuan.png" },
        { id: 1003, type: "user", name: "Trần Linh Chi", username: "linhchi", avatar: "avatar_linhchi.png" },
      ].filter((u) => u.id !== userId);

      const formattedGroups = (groupsData.groups || []).map((g: any) => ({
        id: g.id,
        type: "group",
        name: g.name,
        avatar: g.avatarFilename,
        lastMessage: g.description || "Nhóm mới tạo",
      }));

      setConversations([...formattedGroups, ...mockUsers]);
    } catch (e) {
      console.error(e);
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

  // 4. Kết nối Supabase Realtime để lắng nghe tin nhắn mới tức thời!
  useEffect(() => {
    if (!currentUser) return;

    // Lắng nghe sự kiện INSERT trên bảng "messages"
    const channel = supabase
      .channel("public:messages")
      .on(
        "postgres_changes",
        { event: "INSERT", schema: "public", table: "messages" },
        (payload) => {
          const newMsg = payload.new;

          // Kiểm tra xem tin nhắn này có thuộc hội thoại đang mở hay không
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
              // Format lại key từ snake_case của Postgres sang camelCase của Prisma
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

              setMessages((prev) => [...prev, formattedMsg]);
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

  // 5. Gửi tin nhắn mới
  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim() || !activeChat) return;

    const text = inputText;
    setInputText("");

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

      if (!res.ok) {
        console.error("Gửi tin nhắn thất bại");
      }
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="bg-[var(--card-bg)] border border-[var(--card-border)] rounded-3xl overflow-hidden shadow-premium h-[75vh] flex">
      {/* Sidebar - Conversations list */}
      <div className="w-80 border-r border-[var(--card-border)] flex flex-col shrink-0">
        <div className="p-4 border-b border-[var(--card-border)]">
          <h2 className="font-extrabold text-lg">Trò chuyện</h2>
        </div>
        <div className="flex-1 overflow-y-auto p-2 space-y-1">
          {conversations.map((chat) => (
            <div
              key={`${chat.type}-${chat.id}`}
              onClick={() => setActiveChat(chat)}
              className={`flex items-center gap-3 p-3 rounded-2xl cursor-pointer transition-colors ${
                activeChat && activeChat.id === chat.id && activeChat.type === chat.type
                  ? "bg-primary/10 text-primary"
                  : "hover:bg-gray-100 dark:hover:bg-[#202024]"
              }`}
            >
              <div className="relative">
                <img
                  src={`/uploads/avatars/${chat.avatar}`}
                  alt={chat.name}
                  className="w-11 h-11 rounded-xl object-cover"
                  onError={(e) => {
                    e.currentTarget.src = "/assets/images/icons/icon-192x192.png";
                  }}
                />
                <div className="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-accent-green border-2 border-[var(--card-bg)] rounded-full" />
              </div>
              <div className="overflow-hidden flex-1">
                <div className="flex justify-between items-center mb-0.5">
                  <h4 className="font-bold text-xs truncate text-gray-800 dark:text-gray-200">{chat.name}</h4>
                </div>
                <p className="text-[10px] text-gray-400 font-medium truncate uppercase">
                  {chat.type === "group" ? "Nhóm chat" : "Cá nhân"}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Main Chat Area */}
      <div className="flex-1 flex flex-col justify-between bg-gray-50/50 dark:bg-[#131316]">
        {activeChat ? (
          <>
            {/* Active chat header */}
            <div className="px-6 py-3 bg-[var(--card-bg)] border-b border-[var(--card-border)] flex items-center justify-between z-10">
              <div className="flex items-center gap-3">
                <img
                  src={`/uploads/avatars/${activeChat.avatar}`}
                  alt={activeChat.name}
                  className="w-10 h-10 rounded-xl object-cover"
                />
                <div>
                  <h3 className="font-extrabold text-sm">{activeChat.name}</h3>
                  <span className="text-[10px] text-accent-green font-bold flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-accent-green animate-ping" />
                    Đang hoạt động
                  </span>
                </div>
              </div>

              <div className="flex gap-2">
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400"><Phone className="w-4.5 h-4.5" /></button>
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400"><Video className="w-4.5 h-4.5" /></button>
                <button className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400"><MoreVertical className="w-4.5 h-4.5" /></button>
              </div>
            </div>

            {/* Messages box */}
            <div className="flex-1 overflow-y-auto p-6 space-y-4">
              {loading ? (
                <div className="flex items-center justify-center h-full">
                  <div className="w-8 h-8 border-3 border-primary border-t-transparent rounded-full animate-spin" />
                </div>
              ) : messages.length > 0 ? (
                messages.map((msg) => {
                  const isMine = msg.senderId === currentUser.id;
                  return (
                    <div key={msg.id} className={`flex gap-3 max-w-[70%] ${isMine ? "ml-auto flex-row-reverse" : ""}`}>
                      {!isMine && (
                        <img
                          src={`/uploads/avatars/${activeChat.avatar}`}
                          alt="Sender avatar"
                          className="w-8 h-8 rounded-lg object-cover shrink-0"
                        />
                      )}
                      <div className="space-y-1">
                        <div className={`p-3 rounded-2xl text-xs font-medium leading-relaxed ${
                          isMine
                            ? "bg-primary text-white rounded-tr-none shadow-md"
                            : "bg-[var(--card-bg)] text-gray-800 dark:text-gray-200 border border-[var(--card-border)] rounded-tl-none"
                        }`}>
                          {msg.messageText}
                        </div>
                        <p className={`text-[8.5px] text-gray-400 font-medium ${isMine ? "text-right" : ""}`}>
                          {new Date(msg.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          {isMine && msg.isRead && " • Đã đọc"}
                        </p>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-gray-400 space-y-2">
                  <MessageSquare className="w-8 h-8 opacity-40" />
                  <p className="text-xs font-semibold">Chưa có tin nhắn nào. Bắt đầu ngay!</p>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Input Message box */}
            <div className="p-4 bg-[var(--card-bg)] border-t border-[var(--card-border)]">
              <form onSubmit={handleSendMessage} className="flex gap-2 items-center">
                <button
                  type="button"
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 transition-colors"
                >
                  <Image className="w-5 h-5" />
                </button>
                <button
                  type="button"
                  className="p-2 hover:bg-gray-100 dark:hover:bg-[#202024] rounded-xl text-gray-400 transition-colors"
                >
                  <Plus className="w-5 h-5" />
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
          <div className="flex flex-col items-center justify-center h-full text-gray-400 space-y-3">
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
    </div>
  );
}
