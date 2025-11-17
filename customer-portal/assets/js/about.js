document.addEventListener("DOMContentLoaded", function() {

    // Select all elements that should fade in
    const fadeElems = document.querySelectorAll('.fade-in');

    // Options for the Intersection Observer
    const observerOptions = {
        root: null, // observes intersections relative to the viewport
        rootMargin: '0px',
        threshold: 0.2 // Trigger when 20% of the element is visible
    };

    // Create the Intersection Observer
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            // If the element is intersecting (visible)
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Stop observing this element once it's visible
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe each fade-in element
    fadeElems.forEach(elem => {
        observer.observe(elem);
    });

});