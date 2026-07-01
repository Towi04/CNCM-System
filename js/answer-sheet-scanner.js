/**
 * OMR Answer Sheet — detección robusta (foto, captura de pantalla, PDF).
 */
window.AnswerSheetScanner = (function () {
  const DEFAULT_CANON_W = 1020;
  const DEFAULT_CANON_H = 660;
  const DEFAULT_FILL_THRESHOLD = 140;

  let layout = null;

  function fillThreshold() {
    return layout?.fill_threshold ?? DEFAULT_FILL_THRESHOLD;
  }

  function canonSize() {
    return {
      w: layout?.canon_w || DEFAULT_CANON_W,
      h: layout?.canon_h || DEFAULT_CANON_H,
    };
  }

  function lumAt(data, w, x, y) {
    x = Math.max(0, Math.min(w - 1, Math.round(x)));
    y = Math.max(0, Math.min(Math.floor(data.length / (w * 4)) - 1, Math.round(y)));
    const i = (y * w + x) * 4;
    return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
  }

  function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        resolve(img);
      };
      img.onerror = reject;
      img.src = url;
    });
  }

  function imageToCanvas(img, maxW) {
    const scale = Math.min(1, maxW / img.width);
    const w = Math.round(img.width * scale);
    const h = Math.round(img.height * scale);
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, w, h);
    return c;
  }

  /** Recorta márgenes blancos externos (capturas de pantalla). */
  function autoCropContent(canvas) {
    const w = canvas.width;
    const h = canvas.height;
    const ctx = canvas.getContext('2d');
    const img = ctx.getImageData(0, 0, w, h);
    const d = img.data;
    const threshold = 248;
    let minX = w, minY = h, maxX = 0, maxY = 0;
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const i = (y * w + x) * 4;
        const lum = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
        if (lum < threshold) {
          if (x < minX) minX = x;
          if (y < minY) minY = y;
          if (x > maxX) maxX = x;
          if (y > maxY) maxY = y;
        }
      }
    }
    if (maxX <= minX || maxY <= minY) return canvas;
    const pad = Math.max(4, Math.round(Math.min(w, h) * 0.008));
    minX = Math.max(0, minX - pad);
    minY = Math.max(0, minY - pad);
    maxX = Math.min(w - 1, maxX + pad);
    maxY = Math.min(h - 1, maxY + pad);
    const cw = maxX - minX + 1;
    const ch = maxY - minY + 1;
    if (cw < w * 0.25 || ch < h * 0.25) return canvas;
    const out = document.createElement('canvas');
    out.width = cw;
    out.height = ch;
    out.getContext('2d').drawImage(canvas, minX, minY, cw, ch, 0, 0, cw, ch);
    return out;
  }

  function cropRegion(canvas, yFrac0, yFrac1) {
    const y0 = Math.floor(canvas.height * yFrac0);
    const y1 = Math.floor(canvas.height * yFrac1);
    const rh = Math.max(1, y1 - y0);
    const c = document.createElement('canvas');
    c.width = canvas.width;
    c.height = rh;
    c.getContext('2d').drawImage(canvas, 0, y0, canvas.width, rh, 0, 0, canvas.width, rh);
    return c;
  }

  /** Busca el centro del cuadrado negro de registro en una esquina. */
  function findFiducialInCorner(data, w, h, corner) {
    const qW = Math.floor(w * 0.14);
    const qH = Math.floor(h * 0.14);
    let x0, y0, x1, y1;
    switch (corner) {
      case 'tl': x0 = 0; y0 = 0; x1 = qW; y1 = qH; break;
      case 'tr': x0 = w - qW; y0 = 0; x1 = w; y1 = qH; break;
      case 'bl': x0 = 0; y0 = h - qH; x1 = qW; y1 = h; break;
      default: x0 = w - qW; y0 = h - qH; x1 = w; y1 = h;
    }
    const win = Math.max(6, Math.floor(Math.min(w, h) * 0.018));
    let best = null;
    let bestAvg = 255;
    for (let y = y0; y <= y1 - win; y += 3) {
      for (let x = x0; x <= x1 - win; x += 3) {
        let sum = 0;
        let n = 0;
        for (let dy = 0; dy < win; dy++) {
          for (let dx = 0; dx < win; dx++) {
            sum += lumAt(data, w, x + dx, y + dy);
            n++;
          }
        }
        const avg = sum / n;
        if (avg < bestAvg) {
          bestAvg = avg;
          best = { x: x + win / 2, y: y + win / 2 };
        }
      }
    }
    if (!best || bestAvg > 120) return null;
    return best;
  }

  function findCorners(canvas) {
    const w = canvas.width;
    const h = canvas.height;
    const ctx = canvas.getContext('2d');
    const d = ctx.getImageData(0, 0, w, h).data;

    const tl = findFiducialInCorner(d, w, h, 'tl');
    const tr = findFiducialInCorner(d, w, h, 'tr');
    const bl = findFiducialInCorner(d, w, h, 'bl');
    const br = findFiducialInCorner(d, w, h, 'br');

    if (tl && tr && bl && br) {
      return [tl, tr, br, bl];
    }

    const mX = w * 0.08;
    const mY = h * 0.08;
    return [
      { x: mX, y: mY },
      { x: w - mX, y: mY },
      { x: w - mX, y: h - mY },
      { x: mX, y: h - mY },
    ];
  }

  function warpPerspective(srcCanvas, corners, outW, outH) {
    const out = document.createElement('canvas');
    out.width = outW;
    out.height = outH;
    const ctx = out.getContext('2d', { willReadFrequently: true });
    const srcCtx = srcCanvas.getContext('2d');
    const srcData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);
    const outData = ctx.createImageData(outW, outH);
    const [tl, tr, br, bl] = corners;
    const sw = srcCanvas.width;
    const sh = srcCanvas.height;

    for (let y = 0; y < outH; y++) {
      const v = outH > 1 ? y / (outH - 1) : 0;
      for (let x = 0; x < outW; x++) {
        const u = outW > 1 ? x / (outW - 1) : 0;
        const sx = (1 - u) * (1 - v) * tl.x + u * (1 - v) * tr.x + u * v * br.x + (1 - u) * v * bl.x;
        const sy = (1 - u) * (1 - v) * tl.y + u * (1 - v) * tr.y + u * v * br.y + (1 - u) * v * bl.y;
        const si = (Math.round(sy) * sw + Math.round(sx)) * 4;
        const oi = (y * outW + x) * 4;
        if (si >= 0 && si < srcData.data.length - 3) {
          outData.data[oi] = srcData.data[si];
          outData.data[oi + 1] = srcData.data[si + 1];
          outData.data[oi + 2] = srcData.data[si + 2];
          outData.data[oi + 3] = 255;
        } else {
          outData.data[oi] = outData.data[oi + 1] = outData.data[oi + 2] = 255;
          outData.data[oi + 3] = 255;
        }
      }
    }
    ctx.putImageData(outData, 0, 0);
    return out;
  }

  function scaleToCanonical(src, outW, outH) {
    const out = document.createElement('canvas');
    out.width = outW;
    out.height = outH;
    const ctx = out.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, outW, outH);
    ctx.drawImage(src, 0, 0, outW, outH);
    return out;
  }

  function bubbleDarkness(data, w, h, cx, cy, r) {
    const px = Math.round(cx * w);
    const py = Math.round(cy * h);
    const pr = Math.max(5, Math.round(r * w * 1.35));
    let sum = 0;
    let n = 0;
    for (let y = py - pr; y <= py + pr; y++) {
      for (let x = px - pr; x <= px + pr; x++) {
        if (x < 0 || y < 0 || x >= w || y >= h) continue;
        const dx = x - px;
        const dy = y - py;
        if (dx * dx + dy * dy > pr * pr) continue;
        sum += lumAt(data, w, x, y);
        n++;
      }
    }
    return n ? sum / n : 255;
  }

  function pickBubblesDetailed(bubbles, data, w, h, threshold, isMc) {
    const search = isMc ? 0.024 : 0.020;
    const ranked = [];
    for (const b of bubbles) {
      let bestLum = 255;
      for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
          const lum = bubbleDarkness(data, w, h, b.x + dx * search, b.y + dy * search, b.r);
          if (lum < bestLum) bestLum = lum;
        }
      }
      ranked.push({ b, lum: bestLum });
    }
    ranked.sort((a, c) => a.lum - c.lum);
    const luminances = {};
    ranked.forEach((r) => { luminances[r.b.value] = Math.round(r.lum); });
    if (!ranked.length) {
      return { value: null, luminances, ambiguous: true };
    }
    const best = ranked[0];
    const second = ranked[1];
    let value = null;
    let ambiguous = false;
    if (best.lum <= threshold) {
      const gap = second ? second.lum - best.lum : 999;
      const ratio = second ? best.lum / Math.max(1, second.lum) : 0;
      const minGap = isMc ? 6 : 5;
      if (gap >= minGap || ratio <= 0.82 || best.lum <= 90) {
        value = best.b.value;
      } else if (best.lum <= 105 && gap >= 4) {
        value = best.b.value;
        ambiguous = true;
      } else {
        ambiguous = true;
      }
    }
    return { value, luminances, ambiguous, pick: best.b };
  }

  function pickDarkest(bubbles, data, w, h, threshold, isMc) {
    const d = pickBubblesDetailed(bubbles, data, w, h, threshold, isMc);
    return d.pick && d.value ? d.pick : null;
  }

  function decodeRubricGroupDetailed(data, w, h, group, numQuestions, numAspects, threshold) {
    const out = {};
    const details = {};
    for (let q = 0; q < numQuestions; q++) {
      out[q] = {};
      details[q] = {};
      for (let i = 0; i < numAspects; i++) {
        const opts = layout.bubbles.filter(
          b => b.group === group && b.question === q && b.aspect === i
        );
        const d = pickBubblesDetailed(opts, data, w, h, threshold, false);
        details[q][i] = d;
        if (d.value) out[q][i] = d.value;
      }
    }
    return { values: out, details };
  }

  function decodeRubricGroup(data, w, h, group, numQuestions, numAspects, threshold) {
    return decodeRubricGroupDetailed(data, w, h, group, numQuestions, numAspects, threshold).values;
  }

  function decodeScan(warpedCanvas, threshold) {
    if (!layout) throw new Error('Layout no cargado');
    const w = warpedCanvas.width;
    const h = warpedCanvas.height;
    const data = warpedCanvas.getContext('2d').getImageData(0, 0, w, h).data;

    const result = { mc: {}, writing: {}, speaking: {}, mc_details: {}, writing_details: {}, speaking_details: {} };

    for (let q = 1; q <= layout.mc_count; q++) {
      const opts = layout.bubbles.filter(b => b.group === 'mc' && b.question === q);
      const d = pickBubblesDetailed(opts, data, w, h, threshold, true);
      result.mc_details[q] = d;
      if (d.value) result.mc[q] = d.value;
    }

    const wQ = layout.writing_questions || 2;
    const sQ = layout.speaking_questions || 2;
    const wAspects = (layout.writing_aspects || []).length || 5;
    const sAspects = (layout.speaking_aspects || []).length || 5;
    const wDec = decodeRubricGroupDetailed(data, w, h, 'writing', wQ, wAspects, threshold);
    const sDec = decodeRubricGroupDetailed(data, w, h, 'speaking', sQ, sAspects, threshold);
    result.writing = wDec.values;
    result.speaking = sDec.values;
    result.writing_details = wDec.details;
    result.speaking_details = sDec.details;

    return result;
  }

  function countCornerHits(data, w, h) {
    if (!layout) return 0;
    const marks = layout.bubbles.filter(b => b.group === 'corner' || b.group === 'align');
    let hits = 0;
    for (const m of marks) {
      if (bubbleDarkness(data, w, h, m.x, m.y, m.r) < 135) hits++;
    }
    return hits;
  }

  function evaluateWarp(warped, threshold) {
    const w = warped.width;
    const h = warped.height;
    const data = warped.getContext('2d').getImageData(0, 0, w, h).data;
    const decoded = decodeScan(warped, threshold);
    const mcCount = Object.keys(decoded.mc).length;
    const corners = countCornerHits(data, w, h);
    const score = mcCount * 5 + corners * 2;
    return { data: decoded, score, mcCount, corners };
  }

  function scanCanvas(src) {
    const { w: canonW, h: canonH } = canonSize();
    const cropped = autoCropContent(src);
    const aspect = cropped.height / cropped.width;
    const targetAspect = canonH / canonW;

    const attempts = [];

    attempts.push({ canvas: cropped, mode: 'scale', tag: 'scale-full' });
    attempts.push({ canvas: cropped, mode: 'warp', tag: 'warp-full' });

    if (Math.abs(aspect - targetAspect) > 0.15 && aspect > 1.1) {
      attempts.push({ canvas: cropRegion(cropped, 0, 0.52), mode: 'scale', tag: 'scale-top' });
      attempts.push({ canvas: cropRegion(cropped, 0, 0.52), mode: 'warp', tag: 'warp-top' });
      attempts.push({ canvas: cropRegion(cropped, 0.48, 1), mode: 'scale', tag: 'scale-bottom' });
      attempts.push({ canvas: cropRegion(cropped, 0.48, 1), mode: 'warp', tag: 'warp-bottom' });
    }

    const thresholds = [
      fillThreshold(),
      fillThreshold() - 15,
      fillThreshold() + 15,
      fillThreshold() - 25,
    ];

    let best = null;
    let bestScore = -1;

    for (const attempt of attempts) {
      let warped;
      if (attempt.mode === 'scale') {
        warped = scaleToCanonical(attempt.canvas, canonW, canonH);
      } else {
        const corners = findCorners(attempt.canvas);
        warped = warpPerspective(attempt.canvas, corners, canonW, canonH);
      }
      for (const th of thresholds) {
        const ev = evaluateWarp(warped, th);
        if (ev.score > bestScore) {
          bestScore = ev.score;
          best = {
            warped,
            data: ev.data,
            region: attempt.tag,
            mcCount: ev.mcCount,
          };
        }
      }
    }

    if (!best) {
      const warped = scaleToCanonical(cropped, canonW, canonH);
      best = {
        warped,
        data: decodeScan(warped, fillThreshold()),
        region: 'fallback',
        mcCount: 0,
      };
    }
    return best;
  }

  async function scanImage(img) {
    const src = imageToCanvas(img, 1800);
    return scanCanvas(src);
  }

  async function scanFile(file) {
    const img = await loadImageFromFile(file);
    return scanImage(img);
  }

  async function scanVideoFrame(video) {
    const c = document.createElement('canvas');
    c.width = video.videoWidth;
    c.height = video.videoHeight;
    c.getContext('2d').drawImage(video, 0, 0);
    const img = new Image();
    img.src = c.toDataURL('image/jpeg', 0.92);
    await new Promise(r => { img.onload = r; });
    return scanImage(img);
  }

  function setLayout(l) {
    layout = l;
  }

  return {
    setLayout,
    getLayout: () => layout,
    scanFile,
    scanVideoFrame,
    scanImage,
    get CANON_W() { return canonSize().w; },
    get CANON_H() { return canonSize().h; },
  };
})();
