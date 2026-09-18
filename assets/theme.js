/* Shared theme helper: applies the tenant's accent color (chosen by the super admin
   only — see super.php) to the CSS custom properties used across the app.
   - The dark admin panel (assets/style.css) uses --accent plus alpha steps --accent-10…80.
   - The light public site (assets/site.css) uses --accent, --accent-dark, --accent-light,
     --accent-soft (a light tint), computed here via HSL so they stay harmonious for any
     base color a super admin picks, not just the built-in default. */

function hexToRgb(hex) {
  var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
  if (!m) return null;
  return { r: parseInt(m[1], 16), g: parseInt(m[2], 16), b: parseInt(m[3], 16) };
}

function rgbToHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  var max = Math.max(r, g, b), min = Math.min(r, g, b);
  var h, s, l = (max + min) / 2;
  if (max === min) { h = s = 0; }
  else {
    var d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      default: h = (r - g) / d + 4;
    }
    h /= 6;
  }
  return { h: h, s: s, l: l };
}

function hslToRgbStr(h, s, l) {
  function hue2rgb(p, q, t) {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  }
  var r, g, b;
  if (s === 0) { r = g = b = l; }
  else {
    var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    var p = 2 * l - q;
    r = hue2rgb(p, q, h + 1 / 3);
    g = hue2rgb(p, q, h);
    b = hue2rgb(p, q, h - 1 / 3);
  }
  return Math.round(r * 255) + ', ' + Math.round(g * 255) + ', ' + Math.round(b * 255);
}

function applyAccentColor(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return;
  var root = document.documentElement.style;
  var base = rgb.r + ', ' + rgb.g + ', ' + rgb.b;
  root.setProperty('--accent', 'rgb(' + base + ')');

  // Dark-theme admin panel: solid color + alpha steps.
  [10, 15, 25, 30, 35, 40, 45, 50, 55, 60, 80].forEach(function (a) {
    root.setProperty('--accent-' + a, 'rgba(' + base + ', ' + (a / 100) + ')');
  });
  var brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
  root.setProperty('--accent-ink', brightness > 150 ? 'oklch(0.13 0.025 255)' : 'oklch(0.97 0.01 90)');

  // Light-theme public site: darker/lighter solid shades + a soft tint, via HSL
  // so the relationship holds for any hue a super admin picks.
  var hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
  var darkRgb = hslToRgbStr(hsl.h, hsl.s, Math.max(0, hsl.l - 0.12));
  var lightRgb = hslToRgbStr(hsl.h, hsl.s, Math.min(1, hsl.l + 0.2));
  root.setProperty('--accent-dark', 'rgb(' + darkRgb + ')');
  root.setProperty('--accent-light', 'rgb(' + lightRgb + ')');
  root.setProperty('--accent-soft', 'rgba(' + base + ', 0.12)');
  root.setProperty('--site-ink', brightness > 150 ? '#0a2540' : '#ffffff');
}
