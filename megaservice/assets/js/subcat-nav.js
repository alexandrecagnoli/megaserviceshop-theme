/**
 * Flèches de défilement des cartes de sous-catégories du hero.
 * ==============================================
 *
 * Le bandeau de sous-catégories défile horizontalement. Au doigt et au trackpad
 * c'est naturel, mais à la souris sans molette horizontale les cartes hors cadre
 * sont tout simplement inatteignables. On ajoute donc deux flèches.
 *
 * Elles ne s'affichent que si le contenu déborde vraiment — sur une catégorie à
 * quatre sous-catégories, deux boutons inertes seraient du bruit. Le CSS les
 * réserve par ailleurs au pointeur fin (cf. _catalog.scss).
 *
 * Le hero n'est pas remplacé par le filtrage AJAX de PrestaShop (seul
 * #js-product-list l'est), un câblage au chargement suffit donc ; on réévalue
 * seulement au redimensionnement, où le débordement peut apparaître ou
 * disparaître.
 */

// Marge de tolérance : les navigateurs arrondissent scrollLeft au sous-pixel,
// une comparaison stricte laisserait la flèche active en bout de course.
const EPSILON = 2;

function initScroller(scroller) {
  const track = scroller.querySelector('.js-subcat-track');
  const prev = scroller.querySelector('.js-subcat-prev');
  const next = scroller.querySelector('.js-subcat-next');

  if (!track || !prev || !next) {
    return;
  }

  const overflows = () => track.scrollWidth - track.clientWidth > EPSILON;

  const sync = () => {
    if (!overflows()) {
      prev.hidden = true;
      next.hidden = true;
      return;
    }

    prev.hidden = false;
    next.hidden = false;
    prev.disabled = track.scrollLeft <= EPSILON;
    next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - EPSILON;
  };

  // On défile d'un écran moins une carte, pour garder un repère visuel entre
  // deux pages plutôt que de tout renouveler d'un coup.
  const step = () => {
    const card = track.querySelector('.ms-subcat-nav__item');
    const cardWidth = card ? card.getBoundingClientRect().width + 8 : 188;
    return Math.max(cardWidth, track.clientWidth - cardWidth);
  };

  prev.addEventListener('click', () => {
    track.scrollBy({ left: -step(), behavior: 'smooth' });
  });

  next.addEventListener('click', () => {
    track.scrollBy({ left: step(), behavior: 'smooth' });
  });

  track.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync);

  // Les vignettes sont des images de fond : leur chargement ne modifie pas la
  // largeur du rail, inutile d'attendre `load`. Le premier calcul peut en
  // revanche tomber avant la mise en page finale des polices.
  sync();
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(sync).catch(() => {});
  }
}

document.querySelectorAll('.js-subcat-scroller').forEach(initScroller);
