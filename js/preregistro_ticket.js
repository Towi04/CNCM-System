/**
 * Imprime comprobante de apartado de pre-registro.
 */
window.HayPreregistroTicket = {
  imprimirApartado(ticket, ticketUrl) {
    const url = ticketUrl || (ticket?.id_preregistro
      ? 'views/ticket_apartado.php?id=' + ticket.id_preregistro + '&folio=' + encodeURIComponent(ticket.folio || '') + '&print=1'
      : null);
    if (!url) return;
    const w = window.open(url, 'ticket_apartado', 'width=420,height=640,scrollbars=yes');
    if (!w) {
      alert('Permita ventanas emergentes para imprimir el comprobante.');
    }
  },
};
