/**
 * Whisper Client Application Logic
 * Pure Vanilla JavaScript communicating with PHP 8.5 RESTful API
 */

const API_BASE = '/api';

const state = {
  identity: null,
  posts: [],
  currentPost: null,
  activeCategory: 'all',
  activeSort: 'recent',
  searchQuery: '',
  page: 1,
  totalPages: 1,
  ownershipTokens: JSON.parse(localStorage.getItem('whisper_ownership_tokens') || '{}'),
  apiLogs: [],
};

// --- HTTP Client with Telemetry & Token Headers ---
async function apiRequest(endpoint, options = {}) {
  const token = localStorage.getItem('whisper_anon_token');
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(token ? { 'X-Anonymous-Token': token } : {}),
    ...(options.headers || {}),
  };

  const startTime = performance.now();
  const method = options.method || 'GET';

  try {
    const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });
    const elapsed = Math.round(performance.now() - startTime);
    const data = await res.json();

    logApiCall(method, endpoint, res.status, elapsed);

    if (!res.ok) {
      const errMsg = data.error?.message || 'API request failed';
      showToast(errMsg, 'error');
      throw new Error(errMsg);
    }

    return data;
  } catch (err) {
    if (!err.message.includes('API request failed')) {
      logApiCall(method, endpoint, 500, Math.round(performance.now() - startTime));
      showToast(err.message, 'error');
    }
    throw err;
  }
}

function logApiCall(method, url, status, timeMs) {
  state.apiLogs.unshift({ method, url, status, timeMs, time: new Date().toLocaleTimeString() });
  if (state.apiLogs.length > 20) state.apiLogs.pop();
  renderApiLogs();
}

function renderApiLogs() {
  const container = document.getElementById('api-log-list');
  if (!container) return;

  container.innerHTML = state.apiLogs.map(log => `
    <div class="log-entry">
      <span class="log-method ${log.method}">${log.method}</span>
      <span class="log-url">${log.url}</span>
      <span class="log-status ${log.status < 400 ? 'ok' : 'err'}">${log.status}</span>
      <span class="log-time">${log.timeMs}ms &bull; ${log.time}</span>
    </div>
  `).join('');
}

// --- Identity Management ---
async function initIdentity() {
  try {
    const res = await apiRequest('/identity/me');
    state.identity = res.data;
    if (res.data.token) {
      localStorage.setItem('whisper_anon_token', res.data.token);
    }
    renderIdentityChip();
  } catch (e) {
    console.error("Identity init failed:", e);
  }
}

async function refreshIdentity() {
  try {
    const res = await apiRequest('/identity/refresh', { method: 'POST' });
    state.identity = res.data;
    if (res.data.token) {
      localStorage.setItem('whisper_anon_token', res.data.token);
    }
    renderIdentityChip();
    showToast(`New identity generated: ${state.identity.pseudonym}`, 'success');
    loadFeed();
  } catch (e) {
    console.error("Refresh failed:", e);
  }
}

function renderIdentityChip() {
  const nameEl = document.getElementById('user-pseudonym');
  const avatarEl = document.getElementById('user-avatar');
  if (!state.identity) return;

  if (nameEl) nameEl.textContent = state.identity.pseudonym;
  if (avatarEl) {
    avatarEl.style.background = `linear-gradient(135deg, ${state.identity.avatar_gradient.from}, ${state.identity.avatar_gradient.to})`;
  }
}

// --- Feed & Posts ---
async function loadFeed(resetPage = true) {
  if (resetPage) state.page = 1;
  const listEl = document.getElementById('posts-container');
  listEl.innerHTML = `<div class="empty-state">Loading whispers...</div>`;

  const params = new URLSearchParams({
    page: state.page,
    limit: 15,
    sort: state.activeSort,
    ...(state.activeCategory !== 'all' ? { category: state.activeCategory } : {}),
    ...(state.searchQuery ? { q: state.searchQuery } : {}),
  });

  try {
    const res = await apiRequest(`/posts?${params.toString()}`);
    state.posts = res.data || [];
    state.totalPages = res.pagination?.total_pages || 1;
    renderPostsList();
  } catch (e) {
    listEl.innerHTML = `<div class="empty-state">Failed to load whispers.</div>`;
  }
}

function renderPostsList() {
  const container = document.getElementById('posts-container');
  if (!state.posts.length) {
    container.innerHTML = `<div class="empty-state">No whispers found in this category. Be the first to share one!</div>`;
    return;
  }

  container.innerHTML = state.posts.map(post => {
    const isOwner = post.viewer_state?.is_owner || hasOwnershipToken('post', post.id);
    const hasLiked = post.viewer_state?.has_liked;

    return `
      <div class="post-card" onclick="openPost('${post.id}')">
        <div class="post-header">
          <div class="author-meta">
            <span class="avatar-circle" style="background: linear-gradient(135deg, ${post.author.avatar_gradient.from}, ${post.author.avatar_gradient.to})"></span>
            <div>
              <div class="author-name">${escapeHtml(post.author.pseudonym)}</div>
              <div class="post-time">${timeAgo(post.created_at)}</div>
            </div>
          </div>
          <span class="category-tag">#${escapeHtml(post.category)}</span>
        </div>

        <h3 class="post-title">${escapeHtml(post.title)}</h3>
        <p class="post-snippet">${escapeHtml(post.content)}</p>

        <div class="post-footer">
          <div class="action-group">
            <button class="interactive-btn ${hasLiked ? 'liked' : ''}" onclick="toggleLike(event, 'post', ${post.id})">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="${hasLiked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              <span>${post.metrics.likes_count}</span>
            </button>
            <button class="interactive-btn" onclick="openPost('${post.id}')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              <span>${post.metrics.comments_count}</span>
            </button>
            <span>&bull; ${post.metrics.views_count} views</span>
          </div>

          ${isOwner ? `
            <button class="btn btn-sm btn-danger" onclick="deletePost(event, ${post.id})">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              Delete
            </button>
          ` : ''}
        </div>
      </div>
    `;
  }).join('');
}

// --- Post Detail View & Hierarchical Comments Tree ---
async function openPost(postId) {
  const container = document.getElementById('post-detail-container');
  const feedView = document.getElementById('feed-view');
  const detailView = document.getElementById('detail-view');

  feedView.style.display = 'none';
  detailView.style.display = 'block';
  window.scrollTo({ top: 0, behavior: 'smooth' });

  container.innerHTML = `<div class="empty-state">Loading whisper details...</div>`;

  try {
    const res = await apiRequest(`/posts/${postId}`);
    state.currentPost = res.data;
    renderPostDetail();
  } catch (e) {
    container.innerHTML = `<div class="empty-state">Failed to load whisper. <button class="btn btn-secondary btn-sm" onclick="backToFeed()">Back to Feed</button></div>`;
  }
}

function backToFeed() {
  state.currentPost = null;
  document.getElementById('feed-view').style.display = 'block';
  document.getElementById('detail-view').style.display = 'none';
  loadFeed(false);
}

function renderPostDetail() {
  const post = state.currentPost;
  const container = document.getElementById('post-detail-container');
  const isOwner = post.viewer_state?.is_owner || hasOwnershipToken('post', post.id);
  const hasLiked = post.viewer_state?.has_liked;

  container.innerHTML = `
    <button class="btn btn-secondary btn-sm back-btn" onclick="backToFeed()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      Back to Feed
    </button>

    <div class="post-header">
      <div class="author-meta">
        <span class="avatar-circle" style="background: linear-gradient(135deg, ${post.author.avatar_gradient.from}, ${post.author.avatar_gradient.to})"></span>
        <div>
          <div class="author-name">${escapeHtml(post.author.pseudonym)}</div>
          <div class="post-time">${timeAgo(post.created_at)}</div>
        </div>
      </div>
      <span class="category-tag">#${escapeHtml(post.category)}</span>
    </div>

    <h2 class="post-title" style="font-size: 1.4rem;">${escapeHtml(post.title)}</h2>
    <div class="post-full-content">${escapeHtml(post.content)}</div>

    <div class="post-footer">
      <div class="action-group">
        <button class="interactive-btn ${hasLiked ? 'liked' : ''}" onclick="toggleLike(event, 'post', ${post.id})">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="${hasLiked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span id="detail-like-count">${post.metrics.likes_count}</span>
        </button>
        <span>&bull; ${post.metrics.views_count} views</span>
      </div>

      ${isOwner ? `
        <button class="btn btn-sm btn-danger" onclick="deletePost(event, ${post.id}, true)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          Delete Whisper
        </button>
      ` : ''}
    </div>

    <!-- Comments Section -->
    <div class="comments-section">
      <div class="comments-header">
        <span>Comments (${post.comments?.length || 0} threads)</span>
      </div>

      <!-- Root Comment Box -->
      <div class="comment-input-box">
        <textarea id="root-comment-text" class="comment-textarea" placeholder="Share your anonymous perspective..."></textarea>
        <div class="comment-input-actions">
          <button class="btn btn-primary btn-sm" onclick="submitComment(${post.id}, null)">Post Comment</button>
        </div>
      </div>

      <!-- Hierarchical Tree Container -->
      <div class="comments-tree" id="comments-tree-container">
        ${renderCommentsTree(post.comments || [])}
      </div>
    </div>
  `;
}

// Recursive Tree Renderer for Nested Replies (arbitrary depth)
function renderCommentsTree(nodes) {
  if (!nodes || !nodes.length) {
    return `<div class="empty-state" style="padding: 1.5rem 0;">No comments yet. Be the first to reply!</div>`;
  }

  return nodes.map(node => renderCommentNode(node)).join('');
}

function renderCommentNode(comment) {
  const isOwner = comment.viewer_state?.is_owner || hasOwnershipToken('comment', comment.id);
  const hasLiked = comment.viewer_state?.has_liked;
  const isNested = comment.depth > 0;

  const childrenHtml = comment.children && comment.children.length > 0
    ? `<div class="nested-children">${comment.children.map(child => renderCommentNode(child)).join('')}</div>`
    : '';

  return `
    <div class="comment-node ${isNested ? 'is-nested' : ''}" id="comment-${comment.id}">
      <div class="comment-card">
        <div class="comment-header">
          <div class="author-meta">
            <span class="avatar-circle" style="width: 20px; height: 20px; background: linear-gradient(135deg, ${comment.author.avatar_gradient.from}, ${comment.author.avatar_gradient.to})"></span>
            <span class="author-name" style="font-size: 0.85rem;">${escapeHtml(comment.author.pseudonym)}</span>
            ${comment.parent_author ? `<span style="color: var(--text-muted); font-size: 0.75rem;">replying to <b>@${escapeHtml(comment.parent_author)}</b></span>` : ''}
          </div>
          <span class="post-time">${timeAgo(comment.created_at)}</span>
        </div>

        <div class="comment-content">${escapeHtml(comment.content)}</div>

        <div class="comment-footer">
          <div class="action-group">
            <button class="interactive-btn ${hasLiked ? 'liked' : ''}" onclick="toggleLike(event, 'comment', ${comment.id})">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="${hasLiked ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              <span>${comment.metrics.likes_count}</span>
            </button>
            <button class="interactive-btn" onclick="toggleReplyBox(${comment.id})">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"></polyline><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
              <span>Reply</span>
            </button>
          </div>

          ${isOwner ? `
            <button class="btn btn-sm btn-danger" onclick="deleteComment(${comment.id})">Delete</button>
          ` : ''}
        </div>

        <!-- Hidden Inline Reply Form Box -->
        <div id="reply-box-${comment.id}" class="reply-input-wrapper" style="display: none;">
          <div class="comment-input-box" style="margin-bottom: 0.5rem;">
            <textarea id="reply-text-${comment.id}" class="comment-textarea" style="min-height: 50px;" placeholder="Write a reply to ${escapeHtml(comment.author.pseudonym)}..."></textarea>
            <div class="comment-input-actions">
              <button class="btn btn-secondary btn-sm" style="margin-right: 0.5rem;" onclick="toggleReplyBox(${comment.id})">Cancel</button>
              <button class="btn btn-primary btn-sm" onclick="submitComment(${state.currentPost.id}, ${comment.id})">Submit Reply</button>
            </div>
          </div>
        </div>
      </div>

      ${childrenHtml}
    </div>
  `;
}

function toggleReplyBox(commentId) {
  const box = document.getElementById(`reply-box-${commentId}`);
  if (box) {
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
    if (box.style.display === 'block') {
      const textarea = document.getElementById(`reply-text-${commentId}`);
      if (textarea) textarea.focus();
    }
  }
}

// --- Action Handlers: Create Post, Add Comment, Toggle Like, Delete ---
async function createPost() {
  const title = document.getElementById('new-post-title').value.trim();
  const content = document.getElementById('new-post-content').value.trim();
  const category = document.getElementById('new-post-category').value;

  if (!title || !content) {
    showToast('Please fill in both title and whisper content.', 'error');
    return;
  }

  try {
    const res = await apiRequest('/posts', {
      method: 'POST',
      body: JSON.stringify({ title, content, category }),
    });

    if (res.data?.ownership_token) {
      storeOwnershipToken('post', res.data.post.id, res.data.ownership_token);
    }

    closeModal('modal-new-post');
    document.getElementById('new-post-title').value = '';
    document.getElementById('new-post-content').value = '';
    showToast('Whisper published successfully!', 'success');
    loadFeed(true);
  } catch (e) {
    console.error("Create post error:", e);
  }
}

async function submitComment(postId, parentId = null) {
  const textareaId = parentId ? `reply-text-${parentId}` : 'root-comment-text';
  const textarea = document.getElementById(textareaId);
  const content = textarea?.value?.trim();

  if (!content) {
    showToast('Comment cannot be empty.', 'error');
    return;
  }

  try {
    const url = parentId ? `/comments/${parentId}/replies` : `/posts/${postId}/comments`;
    const res = await apiRequest(url, {
      method: 'POST',
      body: JSON.stringify({ content, parent_id: parentId }),
    });

    if (res.data?.ownership_token) {
      storeOwnershipToken('comment', res.data.comment.id, res.data.ownership_token);
    }

    showToast(parentId ? 'Reply submitted!' : 'Comment added!', 'success');
    openPost(postId); // Refresh post and nested comments tree
  } catch (e) {
    console.error("Submit comment error:", e);
  }
}

async function toggleLike(event, targetType, targetId) {
  event.stopPropagation();
  try {
    const res = await apiRequest('/likes/toggle', {
      method: 'POST',
      body: JSON.stringify({ target_type: targetType, target_id: targetId }),
    });

    const result = res.data;

    // Optimistic UI updates
    if (state.currentPost && targetType === 'post' && state.currentPost.id === targetId) {
      state.currentPost.metrics.likes_count = result.likes_count;
      state.currentPost.viewer_state.has_liked = result.is_liked;
      const countEl = document.getElementById('detail-like-count');
      if (countEl) countEl.textContent = result.likes_count;
    }

    // Update in feed list if present
    const feedItem = state.posts.find(p => p.id === targetId && targetType === 'post');
    if (feedItem) {
      feedItem.metrics.likes_count = result.likes_count;
      feedItem.viewer_state = feedItem.viewer_state || {};
      feedItem.viewer_state.has_liked = result.is_liked;
      renderPostsList();
    } else if (state.currentPost && targetType === 'comment') {
      openPost(state.currentPost.id); // Refresh comment tree counts
    }
  } catch (e) {
    console.error("Like toggle error:", e);
  }
}

async function deletePost(event, postId, isDetail = false) {
  if (event) event.stopPropagation();
  if (!confirm('Are you sure you want to delete this whisper?')) return;

  const token = getOwnershipToken('post', postId);
  try {
    await apiRequest(`/posts/${postId}`, {
      method: 'DELETE',
      headers: token ? { 'X-Ownership-Token': token } : {},
    });

    showToast('Whisper deleted.', 'success');
    if (isDetail) {
      backToFeed();
    } else {
      loadFeed(false);
    }
  } catch (e) {
    console.error("Delete post error:", e);
  }
}

async function deleteComment(commentId) {
  if (!confirm('Delete this comment?')) return;

  const token = getOwnershipToken('comment', commentId);
  try {
    await apiRequest(`/comments/${commentId}`, {
      method: 'DELETE',
      headers: token ? { 'X-Ownership-Token': token } : {},
    });

    showToast('Comment deleted.', 'success');
    if (state.currentPost) openPost(state.currentPost.id);
  } catch (e) {
    console.error("Delete comment error:", e);
  }
}

// --- Ownership Token Utilities ---
function storeOwnershipToken(type, id, token) {
  const key = `${type}_${id}`;
  state.ownershipTokens[key] = token;
  localStorage.setItem('whisper_ownership_tokens', JSON.stringify(state.ownershipTokens));
}

function getOwnershipToken(type, id) {
  return state.ownershipTokens[`${type}_${id}`] || null;
}

function hasOwnershipToken(type, id) {
  return Boolean(state.ownershipTokens[`${type}_${id}`]);
}

// --- Modals & System Telemetry ---
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = 'flex';
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.style.display = 'none';
}

async function openStatsModal() {
  openModal('modal-stats');
  const container = document.getElementById('stats-content');
  container.innerHTML = '<div class="empty-state" style="padding: 1.5rem 0;">Loading telemetry...</div>';

  try {
    const res = await apiRequest('/stats');
    const d = res.data;

    container.innerHTML = `
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.5rem;">
        <div style="background: var(--bg-card-solid); border: 1px solid var(--border-default); padding: 0.875rem; border-radius: var(--radius-md);">
          <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em;">Total Whispers</div>
          <div style="font-size: 1.35rem; font-weight: 700; color: #818cf8; margin-top: 0.25rem;">${d.platform.total_posts}</div>
        </div>
        <div style="background: var(--bg-card-solid); border: 1px solid var(--border-default); padding: 0.875rem; border-radius: var(--radius-md);">
          <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em;">Total Reactions</div>
          <div style="font-size: 1.35rem; font-weight: 700; color: #f472b6; margin-top: 0.25rem;">${d.platform.total_likes}</div>
        </div>
        <div style="background: var(--bg-card-solid); border: 1px solid var(--border-default); padding: 0.875rem; border-radius: var(--radius-md);">
          <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em;">Threads & Replies</div>
          <div style="font-size: 1.35rem; font-weight: 700; color: #34d399; margin-top: 0.25rem;">${d.platform.total_comments}</div>
        </div>
        <div style="background: var(--bg-card-solid); border: 1px solid var(--border-default); padding: 0.875rem; border-radius: var(--radius-md);">
          <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em;">Database Engine</div>
          <div style="font-size: 1.1rem; font-weight: 700; color: #fbbf24; margin-top: 0.25rem; text-transform: uppercase;">${d.database.driver}</div>
        </div>
      </div>
      <div style="margin-top: 1rem; padding: 0.75rem 0.875rem; background: var(--bg-input); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-secondary); line-height: 1.7;">
        <div><span style="color: var(--text-dim);">Cache Hit Ratio:</span> <b style="color: var(--text-primary);">${d.cache.hit_ratio_percent}%</b> (${d.cache.hits} hits / ${d.cache.total_requests} reqs)</div>
        <div><span style="color: var(--text-dim);">Server Runtime:</span> <b style="color: var(--text-primary);">PHP ${d.server.php_version}</b> &bull; Memory: ${d.server.memory_usage_mb}MB</div>
      </div>
    `;
  } catch (e) {
    container.innerHTML = '<div class="empty-state" style="padding: 1.5rem 0; color: var(--danger);">Failed to load telemetry.</div>';
  }
}

// --- Helpers ---
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function timeAgo(dateString) {
  const date = new Date(dateString.replace(' ', 'T') + 'Z');
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);

  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

function showToast(msg, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `toast-notification ${type === 'error' ? 'error' : 'success'}`;
  toast.textContent = msg;

  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(6px)';
    toast.style.transition = 'all 200ms ease';
    setTimeout(() => toast.remove(), 250);
  }, 2800);
}

// --- Event Listeners & Boot ---
document.addEventListener('DOMContentLoaded', () => {
  initIdentity();
  loadFeed();

  // Sort tab clicks
  document.querySelectorAll('.sort-tabs .pill').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.sort-tabs .pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.activeSort = btn.dataset.sort;
      loadFeed();
    });
  });

  // Category pill clicks
  document.querySelectorAll('.category-pills .pill').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.category-pills .pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.activeCategory = btn.dataset.category;
      loadFeed();
    });
  });

  // Search input debounce
  let searchTimer;
  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        state.searchQuery = e.target.value.trim();
        loadFeed();
      }, 350);
    });
  }
});
