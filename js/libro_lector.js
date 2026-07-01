(function () {
  const app = document.getElementById('libro-lector-app');
  if (!app || typeof pdfjsLib === 'undefined') return;

  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  const pdfUrl = app.dataset.pdfUrl;
  const wm = app.dataset.watermark || 'CNCM';
  const canvas = document.getElementById('libro-canvas');
  const ctx = canvas.getContext('2d');
  const label = document.getElementById('libro-pagina-label');
  const wmEl = document.getElementById('libro-watermark');
  if (wmEl) wmEl.textContent = wm;

  let pdfDoc = null;
  let pageNum = 1;
  let rendering = false;

  document.addEventListener('contextmenu', (e) => {
    if (app.contains(e.target)) e.preventDefault();
  });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 's')) {
      e.preventDefault();
    }
  });

  function renderPage(num) {
    if (!pdfDoc || rendering) return;
    rendering = true;
    pdfDoc.getPage(num).then((page) => {
      const wrap = document.getElementById('libro-canvas-wrap');
      const maxW = (wrap?.clientWidth || 800) - 32;
      const viewport = page.getViewport({ scale: 1 });
      const scale = Math.min(1.8, maxW / viewport.width);
      const vp = page.getViewport({ scale });
      canvas.height = vp.height;
      canvas.width = vp.width;
      return page.render({ canvasContext: ctx, viewport: vp }).promise;
    }).then(() => {
      if (label) label.textContent = 'Pág. ' + pageNum + ' / ' + pdfDoc.numPages;
      rendering = false;
    }).catch(() => { rendering = false; });
  }

  pdfjsLib.getDocument({ url: pdfUrl, withCredentials: true, disableAutoFetch: false }).promise
    .then((doc) => {
      pdfDoc = doc;
      renderPage(pageNum);
    })
    .catch(() => {
      if (label) label.textContent = 'Error al cargar el libro';
    });

  document.getElementById('libro-prev')?.addEventListener('click', () => {
    if (pageNum <= 1) return;
    pageNum--;
    renderPage(pageNum);
  });
  document.getElementById('libro-next')?.addEventListener('click', () => {
    if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
    pageNum++;
    renderPage(pageNum);
  });
})();
