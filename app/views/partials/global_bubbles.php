<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/**
 * Floating call + message bubbles for every authenticated page.
 * - Voice call partial is auto-included so this tab can RECEIVE calls anywhere.
 * - A floating 💬 button links to the role-appropriate messages/track page.
 * - If the page already has a parcel-specific chat bubble ($chat_parcel_id set),
 *   we skip the global 💬 to avoid overlap.
 */
if (empty($_SESSION['user_id'])) return;
if (!empty($GLOBALS['__dropit_global_bubbles_rendered'])) return;
$GLOBALS['__dropit_global_bubbles_rendered'] = true;

$_role = $_SESSION['role'] ?? '';
$_msg_link = match ($_role) {
    'admin'    => url('admin/messages'),
    'customer' => url('customer/messages'),
    'driver'   => url('driver/messages'),
    default    => url('/'),
};

// Voice call partial auto-registers peer for receiving calls (no dial target).
if (empty($GLOBALS['__dropit_voicecall_included'])) {
    include __DIR__ . '/voice_call.php';
}
?>

<?php if (empty($chat_parcel_id)): ?>
<a href="<?= $_msg_link ?>" id="globalMsgBtn"
   style="position:fixed;bottom:calc(5rem + env(safe-area-inset-bottom,0px));right:1rem;z-index:9999;background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff;"
   class="w-14 h-14 rounded-full shadow-glow flex items-center justify-center relative transition hover:scale-105" title="Messages">
  <?= icon('messages', 'w-7 h-7 dropit-icon-active') ?>
  <span id="globalMsgBadge" data-msg-badge
        class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 hidden">0</span>
</a>
<?php endif; ?>
