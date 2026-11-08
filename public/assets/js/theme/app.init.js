var userSettings = {
  Layout: "vertical", // vertical | horizontal
  SidebarType: "full", // full | mini-sidebar
  BoxedLayout: false, // true | false (100% ancho)
  Direction: "ltr", // ltr | rtl
  Theme: document.documentElement.getAttribute("data-bs-theme") || localStorage.getItem("theme") || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"),
  ColorTheme: "Orange_Theme", // Blue_Theme | Aqua_Theme | Purple_Theme | Green_Theme | Cyan_Theme | Orange_Theme
  cardBorder: false, // true | false
};
