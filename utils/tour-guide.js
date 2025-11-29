class TourGuide {
  constructor(steps) {
    this.steps = steps;
    this.currentStep = 0;
    this.overlay = null;
    this.tooltip = null;
    this.highlightBox = null;
    this.isActive = false;
  }

  // check if user has completed the tour
  hasCompletedTour() {
    return localStorage.getItem('dashboardTourCompleted') === 'true';
  }

  // done tour
  markTourCompleted() {
    localStorage.setItem('dashboardTourCompleted', 'true');
  }

  // start the tour
  start() {
    if (this.isActive) return;
    
    this.isActive = true;
    this.currentStep = 0;
    this.createOverlay();
    this.createTooltip();
    this.createHighlightBox();
    this.showStep(0);
  }

  createOverlay() {
    this.overlay = document.createElement('div');
    this.overlay.id = 'tour-overlay';
    this.overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 9998;
      transition: opacity 0.3s ease;
    `;
    document.body.appendChild(this.overlay);
  }

  createHighlightBox() {
    this.highlightBox = document.createElement('div');
    this.highlightBox.id = 'tour-highlight';
    this.highlightBox.style.cssText = `
      position: absolute;
      border: 3px solid #6fa9c9;
      border-radius: 8px;
      box-shadow: 0 0 0 4px rgba(111, 169, 201, 0.3),
                  0 0 20px rgba(111, 169, 201, 0.5);
      pointer-events: none;
      z-index: 9999;
      transition: all 0.3s ease;
    `;
    document.body.appendChild(this.highlightBox);
  }


  createTooltip() {
    this.tooltip = document.createElement('div');
    this.tooltip.id = 'tour-tooltip';
    this.tooltip.style.cssText = `
      position: absolute;
      background: white;
      border-radius: 12px;
      padding: 20px;
      max-width: 350px;
      z-index: 10000;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      transition: all 0.3s ease;
    `;
    document.body.appendChild(this.tooltip);
  }

  showStep(stepIndex) {
    if (stepIndex < 0 || stepIndex >= this.steps.length) return;

    this.currentStep = stepIndex;
    const step = this.steps[stepIndex];
    const target = document.querySelector(step.target);

    if (!target) {
      console.warn(`Tour target not found: ${step.target}`);
      this.next();
      return;
    }


    this.scrollToTarget(target, () => {
      this.positionHighlight(target);
      
      this.positionTooltip(target, step);
      this.updateTooltipContent(step);
    });
  }


  scrollToTarget(target, callback) {
    const rect = target.getBoundingClientRect();
    const targetTop = window.pageYOffset + rect.top;
    const targetCenter = targetTop - (window.innerHeight / 2) + (rect.height / 2);
    
    window.scrollTo({
      top: Math.max(0, targetCenter),
      behavior: 'smooth'
    });

    setTimeout(callback, 400);
  }

  positionHighlight(target) {
    const rect = target.getBoundingClientRect();
    const padding = 8;
    
    this.highlightBox.style.top = `${window.pageYOffset + rect.top - padding}px`;
    this.highlightBox.style.left = `${rect.left - padding}px`;
    this.highlightBox.style.width = `${rect.width + (padding * 2)}px`;
    this.highlightBox.style.height = `${rect.height + (padding * 2)}px`;
  }


  positionTooltip(target, step) {
    const rect = target.getBoundingClientRect();
    const tooltipRect = this.tooltip.getBoundingClientRect();
    const spacing = 20;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let top, left;
    let position = step.position || 'auto';

    if (position === 'auto') {
      const spaceTop = rect.top;
      const spaceBottom = viewportHeight - rect.bottom;
      const spaceLeft = rect.left;
      const spaceRight = viewportWidth - rect.right;

      if (spaceBottom > tooltipRect.height + spacing) {
        position = 'bottom';
      } else if (spaceTop > tooltipRect.height + spacing) {
        position = 'top';
      } else if (spaceRight > tooltipRect.width + spacing) {
        position = 'right';
      } else if (spaceLeft > tooltipRect.width + spacing) {
        position = 'left';
      } else {
        position = 'bottom'; 
      }
    }

    switch (position) {
      case 'top':
        top = window.pageYOffset + rect.top - tooltipRect.height - spacing;
        left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        break;
      
      case 'bottom':
        top = window.pageYOffset + rect.bottom + spacing;
        left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        break;
      
      case 'left':
        top = window.pageYOffset + rect.top + (rect.height / 2) - (tooltipRect.height / 2);
        left = rect.left - tooltipRect.width - spacing;
        break;
      
      case 'right':
        top = window.pageYOffset + rect.top + (rect.height / 2) - (tooltipRect.height / 2);
        left = rect.right + spacing;
        break;
    }

    // keep tooltip within viewport bounds
    left = Math.max(10, Math.min(left, viewportWidth - tooltipRect.width - 10));
    top = Math.max(10, top);

    this.tooltip.style.top = `${top}px`;
    this.tooltip.style.left = `${left}px`;
  }

  updateTooltipContent(step) {
    const progress = `${this.currentStep + 1} / ${this.steps.length}`;
    
    this.tooltip.innerHTML = `
      <div style="margin-bottom: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 13px; color: #6fa9c9; font-weight: 600;">${progress}</span>
          <button id="tour-close" style="background: none; border: none; font-size: 20px; color: #999; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
        </div>
        <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #1e1e1e;">${step.title}</h3>
        <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">${step.content}</p>
      </div>
      <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
        <button id="tour-skip" style="background: none; border: none; color: #999; cursor: pointer; font-size: 14px; padding: 8px 12px;">Skip Tour</button>
        <div style="display: flex; gap: 8px;">
          ${this.currentStep > 0 ? '<button id="tour-prev" style="background: rgba(111, 169, 201, 0.1); color: #6fa9c9; border: 1px solid #6fa9c9; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">Previous</button>' : ''}
          ${this.currentStep < this.steps.length - 1 
            ? '<button id="tour-next" style="background: linear-gradient(135deg, #6fa9c9, #3e5c78); color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">Next</button>'
            : '<button id="tour-finish" style="background: linear-gradient(135deg, #6fa9c9, #3e5c78); color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">Finish</button>'
          }
        </div>
      </div>
    `;

    this.attachTooltipListeners();
  }

  attachTooltipListeners() {
    const closeBtn = document.getElementById('tour-close');
    const skipBtn = document.getElementById('tour-skip');
    const prevBtn = document.getElementById('tour-prev');
    const nextBtn = document.getElementById('tour-next');
    const finishBtn = document.getElementById('tour-finish');

    if (closeBtn) closeBtn.onclick = () => this.end();
    if (skipBtn) skipBtn.onclick = () => this.end(true);
    if (prevBtn) prevBtn.onclick = () => this.previous();
    if (nextBtn) nextBtn.onclick = () => this.next();
    if (finishBtn) finishBtn.onclick = () => this.end(true);
  }

  next() {
    if (this.currentStep < this.steps.length - 1) {
      this.showStep(this.currentStep + 1);
    }
  }


  previous() {
    if (this.currentStep > 0) {
      this.showStep(this.currentStep - 1);
    }
  }

  end(markComplete = false) {
    if (markComplete) {
      this.markTourCompleted();
    }
    if (this.overlay) this.overlay.remove();
    if (this.tooltip) this.tooltip.remove();
    if (this.highlightBox) this.highlightBox.remove();

    this.isActive = false;
  }
}
const tourSteps = [
  {
    target: '.navbar',
    title: 'Welcome to read! 📚',
    content: 'Let\'s take a quick tour to help you get started with tracking your reading journey.',
    position: 'bottom'
  },
  {
    target: '#notificationBtn',
    title: 'Notifications',
    content: 'Check friend requests and messages here. Stay connected with your reading community!',
    position: 'bottom'
  },
  {
    target: '.glass-primary.clickable-card',
    title: 'Continue Reading',
    content: 'View your upcoming scheduled readings here. Click to see what\'s due today and stay on track.',
    position: 'bottom'
  },
  {
    target: '.glass-danger.clickable-card, .glass-success.clickable-card',
    title: 'Catch Up',
    content: 'Keep track of missed readings here. Don\'t let overdue items pile up!',
    position: 'bottom'
  },
  {
    target: '.kpi-strip',
    title: 'Your Stats at a Glance',
    content: 'Monitor your active challenges, completions, login streak, and friends count all in one place.',
    position: 'bottom'
  },
  {
    target: '.btn-create',
    title: 'Create a Challenge',
    content: 'Start your own reading challenge! Set goals, invite friends, and track your progress together.',
    position: 'left'
  },
  {
    target: '.btn-discover',
    title: 'Discover Challenges',
    content: 'Browse and join public reading challenges created by other users in the community.',
    position: 'left'
  },
  {
    target: '.challenge-filters',
    title: 'Filter Your Challenges',
    content: 'Toggle between active, expired, and completed challenges to organize your view.',
    position: 'bottom'
  },
  {
    target: '.challenge-list',
    title: 'Your Challenges',
    content: 'All your reading challenges appear here. Click any active challenge to view details and mark readings complete.',
    position: 'right'
  },
  {
    target: '.friends-sidebar',
    title: 'Friends List',
    content: 'Connect with friends, send messages, and see who else is reading. Click "Add" to find new reading buddies!',
    position: 'left'
  }
];

// Initialize tour
let dashboardTour;

// Start tour automatically for first-time users
document.addEventListener('DOMContentLoaded', function() {
  dashboardTour = new TourGuide(tourSteps);
  
  // Auto-start tour for first-time users
  if (!dashboardTour.hasCompletedTour()) {
    setTimeout(() => {
      dashboardTour.start();
    }, 1000); // Small delay so page loads fully
  }
});

// Function to manually start tour (called by help button)
function startTour() {
  if (dashboardTour) {
    dashboardTour.start();
  }
}