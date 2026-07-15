<?php
// ============================================================
//  banner_slot.php — Promo banner carousel (customer-facing pages)
//  Usage: require_once 'banner_slot.php'; render_banner_slot($conn, 'INDEX');
//  Valid page keys: INDEX, LOGIN, REGISTER, C_DASHBOARD
//  Renders nothing if there are no active banners for that page.
// ============================================================

function fetch_active_banners(mysqli $conn, string $pageKey): array {
    $stmt = $conn->prepare(
        "SELECT banner_id, title, image_path, link_url
         FROM banners
         WHERE is_active = 1
           AND (target_page = 'ALL' OR target_page = ?)
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY sort_order ASC, created_at DESC"
    );
    $stmt->bind_param('s', $pageKey);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Defense-in-depth: only a relative path or an http(s) absolute URL is
 * considered a safe href — save_banner.php already rejects other schemes at
 * write time, but this guards against any row that predates that check.
 */
function is_safe_banner_link(?string $url): bool {
    if (!$url) return false;
    if (!preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) return true; // relative path, no scheme
    return (bool) preg_match('#^https?://#i', $url);
}

function render_banner_slot(mysqli $conn, string $pageKey): void {
    $banners = fetch_active_banners($conn, $pageKey);
    if (empty($banners)) return;

    $slotId = 'bannerSlot_' . strtolower($pageKey) . '_' . substr(md5(uniqid('', true)), 0, 6);
    $multi  = count($banners) > 1;
    ?>
    <div id="<?= $slotId ?>" class="promo-banner-slot" style="position:relative;width:100%;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);margin-bottom:24px;background:#F1EDE8;">
        <div class="pbs-track" style="display:flex;transition:transform 0.5s ease;">
            <?php foreach ($banners as $b): $hasSafeLink = is_safe_banner_link($b['link_url']); ?>
            <div class="pbs-slide" style="flex:0 0 100%;min-width:100%;">
                <?php if ($hasSafeLink): ?>
                <a href="<?= htmlspecialchars($b['link_url']) ?>" style="display:block;text-decoration:none;">
                <?php endif; ?>
                    <img src="<?= htmlspecialchars($b['image_path']) ?>" alt="<?= htmlspecialchars($b['title']) ?>"
                         style="width:100%;max-height:280px;object-fit:cover;display:block;">
                <?php if ($hasSafeLink): ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($multi): ?>
        <div class="pbs-dots" style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:7px;">
            <?php foreach ($banners as $i => $b): ?>
            <button type="button" class="pbs-dot" data-index="<?= $i ?>"
                    style="width:8px;height:8px;border-radius:50%;border:none;padding:0;cursor:pointer;
                           background:<?= $i === 0 ? 'rgba(255,255,255,0.95)' : 'rgba(255,255,255,0.45)' ?>;"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($multi): ?>
    <script>
    (function () {
        const root  = document.getElementById('<?= $slotId ?>');
        if (!root) return;
        const track = root.querySelector('.pbs-track');
        const dots  = root.querySelectorAll('.pbs-dot');
        const total = <?= count($banners) ?>;
        let index   = 0;

        function goTo(i) {
            index = (i + total) % total;
            track.style.transform = `translateX(-${index * 100}%)`;
            dots.forEach((d, di) => {
                d.style.background = di === index ? 'rgba(255,255,255,0.95)' : 'rgba(255,255,255,0.45)';
            });
        }

        dots.forEach(d => d.addEventListener('click', () => goTo(parseInt(d.dataset.index, 10))));

        let timer = setInterval(() => goTo(index + 1), 5000);
        root.addEventListener('mouseenter', () => clearInterval(timer));
        root.addEventListener('mouseleave', () => { timer = setInterval(() => goTo(index + 1), 5000); });
    })();
    </script>
    <?php endif; ?>
    <?php
}
