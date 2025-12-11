<?php
/**
 * Plugin Name: AhoToolbar
 * Description: Toolbar hỗ trợ LMS & Flashcard. Fix lỗi giao diện Audio chồng chéo & Tối ưu hiển thị mobile.
 * Version: 4.0
 * Author: Aho Web
 */

if (!defined('ABSPATH')) {
    exit;
}

class CustomToolbar {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_toolbar_html'));
        
        // Ajax Hooks
        add_action('wp_ajax_save_note', array($this, 'save_note'));
        add_action('wp_ajax_nopriv_save_note', array($this, 'save_note'));
        add_action('wp_ajax_get_note', array($this, 'get_note'));
        add_action('wp_ajax_nopriv_get_note', array($this, 'get_note'));
        add_action('wp_ajax_ahotoolbar_toggle_favorite', array($this, 'ajax_toggle_favorite'));
        add_action('wp_ajax_nopriv_ahotoolbar_toggle_favorite', array($this, 'ajax_toggle_favorite')); 
        add_action('wp_ajax_ahotoolbar_get_favorites_details', array($this, 'ajax_get_favorites_details'));
        add_action('wp_ajax_nopriv_ahotoolbar_get_favorites_details', array($this, 'ajax_get_favorites_details'));
        add_action('wp_ajax_ahotoolbar_sync_fav_cards', array($this, 'ajax_sync_fav_cards'));
        add_action('wp_ajax_nopriv_ahotoolbar_sync_fav_cards', array($this, 'ajax_sync_fav_cards'));

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('dashicons');
        // Load MEJS for compatibility
        wp_enqueue_style('wp-mediaelement');
        wp_enqueue_script('wp-mediaelement');
    }
    
    public function add_toolbar_html() {
        $guide_content = get_option('toolbar_guide_content', '');
        $current_user_id = get_current_user_id();
        
        // Load Data
        $server_favorites = $current_user_id ? get_user_meta($current_user_id, 'ahotoolbar_favorite_courses', true) : [];
        if (!is_array($server_favorites)) $server_favorites = [];

        $server_fav_cards = $current_user_id ? get_user_meta($current_user_id, 'ahotoolbar_favorite_flashcards', true) : [];
        if (!is_array($server_fav_cards)) $server_fav_cards = [];

        $js_data = ['is_logged_in' => (bool)$current_user_id, 'fav_courses' => $server_favorites, 'fav_cards' => $server_fav_cards];
        echo '<script>var ahotoolbar_server_data = ' . json_encode($js_data) . ';</script>';
        ?>
        
        <!-- TOOLBAR (FAN MENU) -->
        <div id="aho-fan-container">
            <div id="aho-fan-items">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="aho-fan-item" id="home-btn" title="Trang chủ"><span class="dashicons dashicons-admin-home"></span></a>
                <div class="aho-fan-item" id="courses-btn" title="Khóa học"><span class="dashicons dashicons-book"></span><span id="course-count-badge" class="aho-count-badge" style="display:none;">0</span></div>
                <div class="aho-fan-item" id="cards-btn" title="Flashcard"><span class="dashicons dashicons-index-card"></span><span id="card-count-badge" class="aho-count-badge" style="display:none;">0</span></div>
                <div class="aho-fan-item" id="font-size-btn" title="Cỡ chữ"><span class="dashicons dashicons-editor-textcolor"></span></div>
                <div class="aho-fan-item" id="guide-btn" title="Hướng dẫn"><span class="dashicons dashicons-editor-help"></span></div>
                <div class="aho-fan-item" id="note-btn" title="Ghi chú"><span class="dashicons dashicons-edit"></span></div>
            </div>
            <div id="aho-fan-toggle"><span class="dashicons dashicons-plus"></span></div>
        </div>

        <!-- SCROLL BUTTONS -->
        <div id="aho-scroll-controls">
            <div id="aho-scroll-top" title="Lên đầu trang"><span class="dashicons dashicons-arrow-up-alt2"></span></div>
            <div id="aho-scroll-bottom" title="Xuống cuối trang"><span class="dashicons dashicons-arrow-down-alt2"></span></div>
        </div>
        
        <!-- MODALS (Courses, Cards, Note, Guide) -->
        <div id="courses-modal" class="modal"><div class="modal-content courses-modal-content"><div class="modal-header"><div class="header-title"><span class="header-icon">📚</span><h3>Khóa học đã lưu</h3></div><span class="close">&times;</span></div><div class="modal-body"><div id="favorites-loading">Đang tải...</div><div id="favorites-list"></div><div id="favorites-empty" style="display:none;"><p>Chưa lưu khóa học nào.</p></div></div></div></div>
        
        <div id="cards-modal" class="modal"><div class="modal-content cards-modal-content"><div class="modal-header"><div class="header-title"><span class="header-icon">🗂️</span><h3>Flashcard đã lưu</h3></div><span class="close">&times;</span></div><div class="modal-body" style="padding:0;"><div id="fav-cards-empty" style="padding:30px;text-align:center;display:none;"><p>Chưa lưu thẻ nào.</p></div><div id="fav-cards-review-ui" style="display:none;width:100%;height:100%;padding:20px;box-sizing:border-box;"><div class="fav-card-wrapper" style="perspective:1000px;width:100%;height:300px;margin-bottom:20px;position:relative;"><div id="fav-card-trash" title="Xóa"><span class="dashicons dashicons-trash"></span></div><div class="fav-card-inner" style="position:relative;width:100%;height:100%;text-align:center;transition:transform 0.6s;transform-style:preserve-3d;"><div class="fav-card-front" style="position:absolute;width:100%;height:100%;backface-visibility:hidden;background:#fdfbfb;border:1px solid #ddd;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:15px;overflow-y:auto;"></div><div class="fav-card-back" style="position:absolute;width:100%;height:100%;backface-visibility:hidden;transform:rotateY(180deg);background:#f0f9eb;border:1px solid #c3e6cb;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:15px;overflow-y:auto;"></div></div></div><div class="fav-card-controls" style="display:flex;justify-content:center;gap:15px;"><button id="fav-card-prev" class="button">←</button><span id="fav-card-counter">1/1</span><button id="fav-card-next" class="button">→</button></div></div></div></div></div>
        
        <div id="guide-modal" class="modal"><div class="modal-content guide-modal"><div class="modal-header"><div class="header-title"><span class="header-icon">?</span><h3>Guide</h3></div><span class="close">&times;</span></div><div class="modal-body"><?php echo $this->format_guide_content($guide_content); ?></div></div></div>
        
        <div id="note-modal" class="modal"><div class="modal-content note-modal-content"><div class="modal-header"><div class="header-title"><span class="header-icon">📝</span><h3>Note</h3></div><span class="close">&times;</span></div><div class="modal-body"><textarea id="note-textarea" placeholder="Nhập ghi chú..."></textarea><div class="note-footer"><button id="save-note-btn" class="button-primary">Lưu</button><span id="note-status"></span></div></div></div></div>

        <style>
        /* --- 1. FAN MENU & SCROLL CSS --- */
        #aho-fan-container { position: fixed; bottom: 120px; right: 20px; z-index: 99990; width: 60px; height: 60px; }
        #aho-fan-toggle { width: 60px; height: 60px; background: #2c3e50; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); position: absolute; bottom: 0; right: 0; z-index: 100001; transition: all 0.3s; }
        #aho-fan-toggle:hover { background: #34495e; transform: scale(1.05); }
        #aho-fan-toggle .dashicons { color: #fff; font-size: 24px; transition: 0.3s; }
        #aho-fan-container.active #aho-fan-toggle { background: #e74c3c; }
        #aho-fan-container.active #aho-fan-toggle .dashicons { transform: rotate(45deg); }
        
        #aho-fan-items { position: absolute; bottom: 30px; right: 30px; width: 0; height: 0; }
        .aho-fan-item { position: absolute; width: 44px; height: 44px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba(0,0,0,0.2); color: #2c3e50; text-decoration: none; opacity: 0; transform: scale(0.5); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 100000; pointer-events: none; }
        .aho-fan-item:hover { background: #3498db; color: #fff; }
        #aho-fan-container.active .aho-fan-item { opacity: 1; pointer-events: auto; }
        
        /* Fan out calculation */
        #aho-fan-container.active .aho-fan-item:nth-child(1) { transform: translate(-10px, -130px); }
        #aho-fan-container.active .aho-fan-item:nth-child(2) { transform: translate(-55px, -115px); }
        #aho-fan-container.active .aho-fan-item:nth-child(3) { transform: translate(-95px, -85px); }
        #aho-fan-container.active .aho-fan-item:nth-child(4) { transform: translate(-120px, -45px); }
        #aho-fan-container.active .aho-fan-item:nth-child(5) { transform: translate(-130px, 0px); }
        #aho-fan-container.active .aho-fan-item:nth-child(6) { transform: translate(-95px, 20px); }
        
        .aho-count-badge { position: absolute; top: -5px; right: -5px; width: 16px; height: 16px; border-radius: 50%; background: #e74c3c; color: white; font-size: 10px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
        #aho-fan-items #card-count-badge { background: #f39c12; }

        #aho-scroll-controls { position: fixed; bottom: 20px; right: 20px; z-index: 99990; display: flex; flex-direction: column; gap: 10px; }
        #aho-scroll-top, #aho-scroll-bottom { width: 40px; height: 40px; background: rgba(44, 62, 80, 0.7); border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; backdrop-filter: blur(2px); }

        /* --- 2. MOBILE RESPONSIVE FIXES --- */
        @media (max-width: 768px) {
            /* Shrink FAB */
            #aho-fan-container { width: 45px; height: 45px; right: 15px; bottom: 100px; }
            #aho-fan-toggle { width: 45px; height: 45px; }
            #aho-fan-toggle .dashicons { font-size: 20px; width: 20px; height: 20px; }
            
            /* Shrink Scroll Buttons */
            #aho-scroll-controls { right: 15px; bottom: 15px; }
            #aho-scroll-top, #aho-scroll-bottom { width: 35px; height: 35px; }
            
            /* Adjust Fan Positions for smaller origin */
            #aho-fan-items { bottom: 22px; right: 22px; }
            
            /* Adjust Modal Width */
            .modal-content { width: 95%; margin: 10% auto; max-height: 80vh; }
        }

        /* --- 3. AUDIO PLAYER FIX --- */
        /* Sticky Wrapper: Wraps BOTH the player and our custom bar */
        .ahotoolbar-audio-wrapper {
            transition: all 0.3s ease;
        }
        .ahotoolbar-audio-wrapper.is-sticky {
            position: fixed !important; bottom: 0; left: 0; width: 100%;
            z-index: 999999; background: #fff;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.1);
            border-top: 1px solid #ddd;
            padding: 5px 0 10px 0;
            animation: slideUp 0.3s ease;
            display: flex; flex-direction: column; align-items: center;
        }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

        /* Custom Control Bar (OUTSIDE the player) */
        .aho-audio-controls-bar {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 5px; background: #f0f0f1; padding: 4px 10px; border-radius: 20px;
            width: fit-content; max-width: 100%; overflow-x: auto;
        }
        .aho-audio-btn {
            background: #fff; border: 1px solid #ccc; border-radius: 4px;
            padding: 4px 8px; font-size: 11px; cursor: pointer; color: #555;
            display: flex; align-items: center; gap: 3px; white-space: nowrap;
        }
        .aho-audio-btn:hover { background: #e0e0e0; color: #000; }
        .aho-audio-btn.active { background: #3498db; color: #fff; border-color: #2980b9; }
        .aho-audio-btn i { font-style: normal; font-size: 14px; }
        
        /* Sticky Close Button */
        .aho-audio-close { 
            position: absolute; right: 10px; top: -30px; 
            background: #333; color: #fff; padding: 5px 10px; 
            border-radius: 5px 5px 0 0; cursor: pointer; font-size: 11px; display: none; 
        }
        .ahotoolbar-audio-wrapper.is-sticky .aho-audio-close { display: block; }
        
        /* Modal & Helpers */
        .modal { display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); }
        .modal-content { background: #fff; margin: 5% auto; width: 90%; max-width: 500px; border-radius: 12px; display: flex; flex-direction: column; max-height: 85vh; }
        .modal-header { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; border-radius: 12px 12px 0 0; }
        .close { font-size: 24px; cursor: pointer; }
        .modal-body { padding: 20px; overflow-y: auto; }
        .font-size-small * { font-size: 14px !important; } .font-size-normal * { font-size: 16px !important; } .font-size-large * { font-size: 18px !important; } .font-size-extra-large * { font-size: 20px !important; }
        .fav-card-wrapper.is-flipped .fav-card-inner { transform: rotateY(180deg); }
        .flying-heart { position: fixed; pointer-events: none; z-index: 100000; font-size: 24px; color: #e74c3c; transition: all 0.8s; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            
            // 1. UI: FAB & SCROLL
            $("#aho-fan-toggle").click(function() { $("#aho-fan-container").toggleClass("active"); });
            $(document).click(function(e) { if (!$(e.target).closest('#aho-fan-container').length) $("#aho-fan-container").removeClass("active"); });
            $("#aho-scroll-top").click(function() { $("html, body").animate({ scrollTop: 0 }, "slow"); });
            $("#aho-scroll-bottom").click(function() { $("html, body").animate({ scrollTop: $(document).height() }, "slow"); });

            // 2. AUDIO ENGINE (NO INJECTION, SEPARATE BAR)
            const AhoAudioEngine = {
                activePlayer: null,
                
                init: function() {
                    this.scan();
                    // Watch for LMS Ajax content
                    const obs = new MutationObserver((muts) => {
                        let f = false;
                        muts.forEach(m => {
                            if(m.addedNodes.length) {
                                m.addedNodes.forEach(n => {
                                    if(n.nodeType===1 && (n.tagName==='AUDIO'||n.querySelector?.('audio')||n.classList?.contains('mejs-container'))) f=true;
                                });
                            }
                        });
                        if(f) setTimeout(() => this.scan(), 500);
                    });
                    obs.observe(document.body, {childList:true, subtree:true});

                    // Global Events
                    document.addEventListener('play', (e) => {
                        if(e.target.tagName==='AUDIO'||e.target.tagName==='VIDEO') this.handlePlay(e.target);
                    }, true);

                    $(document).keydown((e) => {
                        if(!this.activePlayer || this.activePlayer.paused) return;
                        if($(e.target).is('input, textarea')) return;
                        if(e.keyCode===37) { e.preventDefault(); this.seek(-5); }
                        if(e.keyCode===39) { e.preventDefault(); this.seek(5); }
                    });
                },

                scan: function() {
                    // Wrap all unwrapped audio/mejs in our container
                    $('audio, .mejs-container').each(function() {
                        const el = $(this);
                        // Skip if already processed or inside our wrapper
                        if(el.closest('.ahotoolbar-audio-wrapper').length) return;
                        
                        // Identify the main element to wrap
                        let target = el;
                        if(el.hasClass('mejs-container')) {
                            // It's MEJS, find the audio inside to bind events, but wrap the container
                            // Do nothing here, wait for the audio tag inside to be processed? 
                            // Actually, better to wrap the container.
                        } else if (el[0].tagName === 'AUDIO') {
                            // Native audio. If it has mejs class parent, skip (handled by parent)
                            if(el.closest('.mejs-container').length) return;
                        }

                        // WRAP IT
                        AhoAudioEngine.createWrapper(target);
                    });
                },

                createWrapper: function(playerEl) {
                    // Create wrapper
                    const wrapper = $('<div class="ahotoolbar-audio-wrapper"></div>');
                    // Create Control Bar
                    const bar = $(`
                        <div class="aho-audio-controls-bar">
                            <button class="aho-audio-btn aho-rw"><i>⏪</i> -10s</button>
                            <button class="aho-audio-btn aho-loop"><i>🔁</i> Loop</button>
                            <button class="aho-audio-btn aho-ab" style="font-weight:bold;">A-B</button>
                            <button class="aho-audio-btn aho-fw"><i>⏩</i> +10s</button>
                        </div>
                    `);
                    const closeBtn = $('<div class="aho-audio-close">✕ Thu gọn</div>');
                    
                    playerEl.wrap(wrapper);
                    const wrapRef = playerEl.parent();
                    wrapRef.append(bar).prepend(closeBtn);

                    // Find actual audio element for logic
                    let media = playerEl[0];
                    if(playerEl.hasClass('mejs-container')) {
                        media = playerEl.find('audio, video')[0];
                    }
                    if(!media) return; // Should not happen

                    // Bind Events
                    bar.find('.aho-rw').click((e) => { e.preventDefault(); media.currentTime = Math.max(0, media.currentTime - 10); });
                    bar.find('.aho-fw').click((e) => { e.preventDefault(); media.currentTime = Math.min(media.duration, media.currentTime + 10); });
                    bar.find('.aho-loop').click(function(e) { 
                        e.preventDefault(); media.loop = !media.loop; $(this).toggleClass('active'); 
                    });
                    
                    this.setupAB(media, bar.find('.aho-ab'));

                    // Sticky Close
                    closeBtn.click((e) => {
                        e.preventDefault(); e.stopPropagation();
                        wrapRef.removeClass('is-sticky');
                    });
                },

                setupAB: function(media, btn) {
                    let state = 0; // 0=Off, 1=A, 2=AB
                    let a=0, b=0;
                    btn.click((e) => {
                        e.preventDefault();
                        if(state===0) { state=1; a=media.currentTime; btn.text('A-?').addClass('active'); }
                        else if(state===1) { state=2; b=media.currentTime; if(b<=a){state=0; btn.text('A-B').removeClass('active');} else { btn.text('A-B On'); } }
                        else { state=0; btn.text('A-B').removeClass('active'); }
                    });
                    media.addEventListener('timeupdate', () => {
                        if(state===2 && media.currentTime >= b) { media.currentTime = a; media.play(); }
                    });
                },

                seek: function(val) {
                    if(this.activePlayer) this.activePlayer.currentTime += val;
                },

                handlePlay: function(media) {
                    this.activePlayer = media;
                    $('.ahotoolbar-audio-wrapper').removeClass('is-sticky'); // Unstick others
                    
                    const wrapper = $(media).closest('.ahotoolbar-audio-wrapper');
                    if(wrapper.length) {
                        wrapper.addClass('is-sticky');
                    }
                }
            };
            AhoAudioEngine.init();


            // 3. OTHER LOGIC (Favorites, Notes...)
            let fontSizeLevel=1; const fsClasses=["font-size-small","font-size-normal","font-size-large","font-size-extra-large"];
            if(localStorage.getItem("toolbar-font-size")) { fontSizeLevel=parseInt(localStorage.getItem("toolbar-font-size")); applyFS(); }
            $("#font-size-btn").click(function(){ fontSizeLevel=(fontSizeLevel+1)%4; applyFS(); localStorage.setItem("toolbar-font-size", fontSizeLevel); });
            function applyFS(){ const t=".entry-content,.post-content,.tutor-course-content,.ahovn-lms-single-item-view"; fsClasses.forEach(c=>$(t).removeClass(c)); $(t).addClass(fsClasses[fontSizeLevel]); }

            $(".close").click(function(){ $(this).closest(".modal").fadeOut(200); });
            $(window).click(function(e){ if($(e.target).hasClass("modal")) $(".modal").fadeOut(200); });
            $("#guide-btn").click(function(){ $("#guide-modal").css("display","flex").hide().fadeIn(200); });
            $("#note-btn").click(function(){ $("#note-modal").css("display","flex").hide().fadeIn(200); loadNote(); });
            $("#save-note-btn").click(function(){ $.post(toolbar_ajax.ajax_url, {action:"save_note", note:$("#note-textarea").val(), nonce:toolbar_ajax.nonce}, function(r){ $("#note-status").text(r.success?r.data:"Lỗi").show().delay(2000).fadeOut(); }); });
            function loadNote(){ $.post(toolbar_ajax.ajax_url, {action:"get_note", nonce:toolbar_ajax.nonce}, function(r){ if(r.success) $("#note-textarea").val(r.data); }); }

            // Data
            const sData = typeof ahotoolbar_server_data !== 'undefined' ? ahotoolbar_server_data : {is_logged_in:false, fav_courses:[], fav_cards:[]};
            let favIds = sData.is_logged_in ? sData.fav_courses.map(String) : JSON.parse(localStorage.getItem('ahotoolbar_favorites')||'[]');
            let favCards = sData.is_logged_in ? sData.fav_cards : JSON.parse(localStorage.getItem('ahotoolbar_fav_flashcards')||'[]');
            updateBadge();

            function injectHearts() {
                $('.ahovn-lms-course-wrapper, .type-ahovn_lms_course, .course-item').each(function(){
                    if($(this).find('.course-heart-btn').length) return;
                    let id = $(this).data('course-id')||$(this).data('item-id')||$(this).attr('id')?.replace('post-','');
                    if(!id) return; id=String(id);
                    const isF = favIds.includes(id);
                    const btn = $(`<div class="course-heart-btn ${isF?'active':''}"><i>${isF?'♥':'♡'}</i></div>`);
                    if($(this).hasClass('ahovn-lms-course-wrapper')) btn.addClass('single-course-heart');
                    btn.click(function(e){ e.preventDefault(); e.stopPropagation(); toggleFav(id, $(this)); });
                    $(this).append(btn);
                });
            }
            injectHearts(); $(document).ajaxComplete(injectHearts);

            function toggleFav(id, btn) {
                const i = favIds.indexOf(id); let a='add';
                if(i>-1) { favIds.splice(i,1); a='remove'; btn.removeClass('active').find('i').text('♡'); }
                else { favIds.push(id); btn.addClass('active').find('i').text('♥'); anim(btn, $("#courses-btn")); }
                updateBadge();
                if(sData.is_logged_in) $.post(toolbar_ajax.ajax_url, {action:'ahotoolbar_toggle_favorite', course_id:id, do_action:a, nonce:toolbar_ajax.nonce});
                else localStorage.setItem('ahotoolbar_favorites', JSON.stringify(favIds));
            }

            $(document).on('ahovn_lms_toggle_fav_card', function(e, d){
                if(d.action==='add') { if(!favCards.some(c=>c.id===d.card.id)) { favCards.push(d.card); anim(d.element, $("#cards-btn")); } }
                else { favCards=favCards.filter(c=>c.id!==d.card.id); }
                updateBadge(); localStorage.setItem('ahotoolbar_fav_flashcards', JSON.stringify(favCards));
                if(sData.is_logged_in) $.post(toolbar_ajax.ajax_url, {action:'ahotoolbar_sync_fav_cards', cards:JSON.stringify(favCards), nonce:toolbar_ajax.nonce});
            });

            function updateBadge(){ 
                $('#course-count-badge').text(favIds.length).toggle(favIds.length>0); 
                $('#card-count-badge').text(favCards.length).toggle(favCards.length>0); 
            }
            $("#courses-btn").click(function(){ $("#courses-modal").css("display","flex").hide().fadeIn(200); renderFavs(); });
            $("#cards-btn").click(function(){ $("#cards-modal").css("display","flex").hide().fadeIn(200); reviewCards(); });

            // Favorites Modal
            window.removeFavoriteFromList=function(id){
                if(!confirm('Xóa?')) return; id=String(id); $(`#fav-row-${id}`).remove();
                favIds=favIds.filter(x=>x!==id); updateBadge(); toggleFav(id, $());
                if(!favIds.length) $('#favorites-empty').show();
            };
            function renderFavs(){
                const c=$('#favorites-list').empty(); if(!favIds.length){ $('#favorites-empty').show(); return; } $('#favorites-empty').hide();
                $.post(toolbar_ajax.ajax_url, {action:'ahotoolbar_get_favorites_details', ids:favIds, nonce:toolbar_ajax.nonce}, function(r){
                    if(r.success) r.data.forEach(x=>c.append(`<div id="fav-row-${x.id}" style="display:flex;justify-content:space-between;padding:10px;border-bottom:1px solid #eee;"><a href="${x.url}">${x.title}</a><span style="cursor:pointer;color:red;" onclick="removeFavoriteFromList(${x.id})">&times;</span></div>`));
                });
            }
            // Card Review
            let cri=0;
            function reviewCards(){
                if(!favCards.length){ $('#fav-cards-empty').show(); $('#fav-cards-review-ui').hide(); return; }
                $('#fav-cards-empty').hide(); $('#fav-cards-review-ui').show(); renderC(cri=0);
            }
            function renderC(i){
                if(i>=favCards.length) cri=i=0; const c=favCards[i];
                $('.fav-card-front').html(c.front); $('.fav-card-back').html(c.back); $('.fav-card-wrapper').removeClass('is-flipped');
                $('#fav-card-counter').text(`${i+1}/${favCards.length}`);
                $('#fav-card-prev').prop('disabled', i===0); $('#fav-card-next').prop('disabled', i===favCards.length-1);
            }
            $('.fav-card-wrapper').click(function(e){ if(!$(e.target).closest('#fav-card-trash').length) $(this).toggleClass('is-flipped'); });
            $('#fav-card-prev').click(function(){ if(cri>0) renderC(--cri); }); $('#fav-card-next').click(function(){ if(cri<favCards.length-1) renderC(++cri); });
            $('#fav-card-trash').click(function(e){ e.stopPropagation(); if(confirm('Xóa?')){ 
                const del=favCards[cri].id; favCards.splice(cri,1); if(cri>=favCards.length) cri=Math.max(0,favCards.length-1);
                updateBadge(); localStorage.setItem('ahotoolbar_fav_flashcards', JSON.stringify(favCards));
                if(sData.is_logged_in) $.post(toolbar_ajax.ajax_url, {action:'ahotoolbar_sync_fav_cards', cards:JSON.stringify(favCards), nonce:toolbar_ajax.nonce});
                $(document).trigger('ahovn_lms_fav_card_removed_external', [del]); reviewCards();
            }});

            function anim(s,e){
                const f=$('<div style="position:fixed;z-index:99999;color:#e74c3c;font-size:20px;">♥</div>');
                if(e.attr('id')==='cards-btn') f.text('🗂️').css('color','#f39c12'); $('body').append(f);
                const sp=s.offset(), ep=e.offset();
                f.css({top:sp.top-$(window).scrollTop(), left:sp.left-$(window).scrollLeft()}).animate({top:ep.top, left:ep.left, opacity:0},800,function(){ $(this).remove(); });
            }
        });
        var toolbar_ajax = { ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>', nonce: '<?php echo wp_create_nonce('toolbar_nonce'); ?>' };
        </script>
        <?php
    }
    // AJAX HANDLERS (Same as before)
    public function ajax_sync_fav_cards() { check_ajax_referer('toolbar_nonce','nonce'); $u=get_current_user_id(); if(!$u) wp_send_json_error(); $c=json_decode(stripslashes($_POST['cards']),true); update_user_meta($u,'ahotoolbar_favorite_flashcards', $c); wp_send_json_success(); }
    public function save_note() { check_ajax_referer('toolbar_nonce','nonce'); $n=sanitize_textarea_field($_POST['note']); $u=get_current_user_id(); if($u) update_user_meta($u,'custom_toolbar_note',$n); else { if(!session_id()) session_start(); update_option('guest_note_'.session_id(),$n); } wp_send_json_success('Lưu thành công!'); }
    public function get_note() { check_ajax_referer('toolbar_nonce','nonce'); $u=get_current_user_id(); $n=$u?get_user_meta($u,'custom_toolbar_note',true):(session_id()?get_option('guest_note_'.session_id(),''):''); wp_send_json_success($n); }
    public function ajax_toggle_favorite() { check_ajax_referer('toolbar_nonce','nonce'); $u=get_current_user_id(); if(!$u) wp_send_json_error(); $c=intval($_POST['course_id']); $a=$_POST['do_action']; $f=get_user_meta($u,'ahotoolbar_favorite_courses',true); if(!is_array($f))$f=[]; if($a==='add'&&!in_array($c,$f))$f[]=$c; else if($a==='remove')$f=array_diff($f,[$c]); update_user_meta($u,'ahotoolbar_favorite_courses',array_values($f)); wp_send_json_success(); }
    public function ajax_get_favorites_details() { check_ajax_referer('toolbar_nonce','nonce'); $ids=isset($_POST['ids'])?array_map('intval',$_POST['ids']):[]; if(!$ids) wp_send_json_success([]); global $wpdb; $posts=get_posts(['post_type'=>'any','include'=>$ids,'numberposts'=>-1]); $d=[]; foreach($posts as $p){ $url=get_permalink($p->ID); if($p->post_type!=='post'&&$p->post_type!=='page'){ $pid=$wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 1",'%[ahovn_lms_course id="'.$p->ID.'"%')); if($pid) $url=get_permalink($pid); } $d[]=['id'=>$p->ID,'title'=>$p->post_title,'url'=>$url]; } wp_send_json_success($d); }
    public function format_guide_content($c) { if(!$c) return '<p>Chưa có hướng dẫn.</p>'; return nl2br(esc_html($c)); }
    public function add_admin_menu() { add_options_page('Toolbar', 'Toolbar', 'manage_options', 'toolbar-settings', array($this, 'admin_page')); }
    public function register_settings() { register_setting('toolbar_settings', 'toolbar_guide_content'); register_setting('toolbar_settings', 'toolbar_more_link'); register_setting('toolbar_settings', 'toolbar_more_text'); }
    public function admin_page() { ?> <div class="wrap"><h1>Toolbar Settings</h1><form method="post" action="options.php"><?php settings_fields('toolbar_settings'); ?><table class="form-table"><tr valign="top"><th scope="row">Guide Content</th><td><textarea name="toolbar_guide_content" rows="10" cols="50"><?php echo esc_textarea(get_option('toolbar_guide_content', '')); ?></textarea></td></tr></table><?php submit_button(); ?></form></div> <?php }
}
new CustomToolbar();