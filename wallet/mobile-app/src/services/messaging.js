import api, { BASE_URL } from './client';
import * as FileSystem from 'expo-file-system';

function attachmentApiUrl(id) {
  // Use v1 route (matches mobile api base). We include BASE_URL to avoid relying on axios baseURL.
  return `${BASE_URL}/api/v1/message-attachments/${id}`;
}

export async function listConversations() {
  const res = await api.get('/conversations');
  return res.data?.conversations || [];
}

export async function ensureDirectConversation(recipientUserId) {
  const res = await api.post('/conversations/direct', { recipient_user_id: recipientUserId });
  return res.data?.conversation_id;
}

export async function getMessages(conversationId, { afterId = 0, limit = 50 } = {}) {
  const res = await api.get(`/conversations/${conversationId}/messages`, {
    params: { after_id: afterId, limit },
  });
  return res.data?.messages || [];
}

export async function sendMessage(conversationId, { body = '', imageAsset = null } = {}) {
  const hasImage = !!imageAsset?.uri;
  const trimmed = String(body || '').trim();

  if (!trimmed && !hasImage) {
    throw new Error('Message body or image is required');
  }

  if (!hasImage) {
    const res = await api.post(`/conversations/${conversationId}/messages`, { body: trimmed });
    return res.data?.message;
  }

  const form = new FormData();
  if (trimmed) form.append('body', trimmed);

  const name = imageAsset.fileName || `message-${Date.now()}.jpg`;
  const type = imageAsset.mimeType || 'image/jpeg';

  form.append('image', {
    uri: imageAsset.uri,
    name,
    type,
  });

  const res = await api.post(`/conversations/${conversationId}/messages`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return res.data?.message;
}

export async function downloadAttachmentToCache(attachmentId, token) {
  const url = attachmentApiUrl(attachmentId);
  const dest = `${FileSystem.cacheDirectory}msg-attach-${attachmentId}`;

  const info = await FileSystem.getInfoAsync(dest);
  if (info.exists && info.uri) return info.uri;

  const res = await FileSystem.downloadAsync(url, dest, {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  });

  return res.uri;
}

