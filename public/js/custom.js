/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 */

"use strict";

$(function () {
  /**
   * Sidebar dropdown yang andal (Stisla).
   *
   * Menggantikan handler default `scripts.js` agar perilaku dropdown konsisten:
   * - Klik parent dropdown SELALU membuka/menutup, meskipun di dalamnya ada menu aktif.
   * - Saat halaman dimuat, dropdown yang berisi menu aktif otomatis dibuka & ditandai active.
   * - Membuka satu dropdown otomatis menutup dropdown lain yang sedang terbuka.
   */

  if ($(".main-sidebar .sidebar-menu").length) {
    initSidebarDropdown();
  }

  function initSidebarDropdown() {
    var sidebarMenu = $(".main-sidebar .sidebar-menu");

    // 1) Auto-open dropdown yang berisi menu aktif saat halaman dimuat
    sidebarMenu.find("li.dropdown").each(function () {
      var me = $(this);
      if (me.find("li.active").length) {
        me.addClass("active");
        me.children(".dropdown-menu").slideDown(0);
      }
    });

    // 2) Toggle dropdown (menggantikan handler default Stisla di scripts.js)
    sidebarMenu
      .find("li a.has-dropdown")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();

        var me = $(this);
        var parent = me.closest("li.dropdown");
        var menu = parent.children(".dropdown-menu");
        var isOpen = parent.hasClass("active");

        // Tutup dropdown lain yang sedang terbuka
        sidebarMenu.find("li.dropdown.active").not(parent).each(function () {
          $(this).removeClass("active");
          $(this).children(".dropdown-menu").slideUp(200);
        });

        // Toggle dropdown yang diklik
        if (isOpen) {
          parent.removeClass("active");
          menu.slideUp(200);
        } else {
          parent.addClass("active");
          menu.slideDown(200);
        }

        refreshSidebarNicescroll();
        return false;
      });
  }

  function refreshSidebarNicescroll() {
    var sidebar = $(".main-sidebar");
    try {
      if (sidebar.length && sidebar.getNiceScroll) {
        var nicescrolls = sidebar.getNiceScroll();
        if (nicescrolls && nicescrolls.length) {
          $.each(nicescrolls, function () {
            this.resize();
          });
        }
      }
    } catch (err) {
      // abaikan — niceScroll kemungkinan belum diinisialisasi
    }
  }
});
