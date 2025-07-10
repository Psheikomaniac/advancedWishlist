/// <reference types="cypress" />

describe('Guest Wishlist', () => {
  beforeEach(() => {
    // Clear cookies and local storage to ensure we're a guest user
    cy.clearCookies();
    cy.clearLocalStorage();
    
    // Visit the homepage
    cy.visit('/');
  });

  it('should allow guests to create a wishlist', () => {
    // Navigate to a product detail page
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    
    // Click the "Add to wishlist" button
    cy.get('button').contains('Add to wishlist').click();
    
    // Since we're a guest, a modal should appear asking to create a guest wishlist
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Verify success message
    cy.get('.alert-success').should('be.visible');
    
    // Verify the product was added to the guest wishlist
    cy.visit('/wishlist/guest');
    cy.get('.wishlist-item').should('have.length', 1);
  });

  it('should allow guests to add multiple products to their wishlist', () => {
    // Create a guest wishlist with one product
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    cy.get('button').contains('Add to wishlist').click();
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Add another product
    cy.visit('/detail/e6a97a8b22a045a9b913492e55b8d4f3');
    cy.get('button').contains('Add to wishlist').click();
    
    // Verify both products are in the wishlist
    cy.visit('/wishlist/guest');
    cy.get('.wishlist-item').should('have.length', 2);
  });

  it('should allow guests to remove products from their wishlist', () => {
    // Create a guest wishlist with one product
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    cy.get('button').contains('Add to wishlist').click();
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Navigate to the guest wishlist
    cy.visit('/wishlist/guest');
    
    // Remove the product
    cy.get('.wishlist-item').first().find('button').contains('Remove').click();
    
    // Verify the product was removed
    cy.get('.wishlist-item').should('have.length', 0);
  });

  it('should merge guest wishlist with customer wishlist on login', () => {
    // Create a guest wishlist with one product
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    cy.get('button').contains('Add to wishlist').click();
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Login
    cy.login();
    
    // A modal should appear asking to merge wishlists
    cy.get('.modal').within(() => {
      cy.get('button').contains('Merge').click();
    });
    
    // Verify the product was merged into the customer's wishlist
    cy.visit('/account/wishlist');
    cy.get('.wishlist-item').should('have.length.at.least', 1);
  });

  it('should allow guests to share their wishlist', () => {
    // Create a guest wishlist with one product
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    cy.get('button').contains('Add to wishlist').click();
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Navigate to the guest wishlist
    cy.visit('/wishlist/guest');
    
    // Click the share button
    cy.get('button').contains('Share').click();
    
    // Enter recipient email
    cy.get('#shareEmail').type('friend@example.com');
    
    // Click share button
    cy.get('button[type="submit"]').contains('Share').click();
    
    // Verify success message
    cy.get('.alert-success').should('contain', 'Wishlist shared successfully');
  });

  it('should persist guest wishlist across browser sessions', () => {
    // Create a guest wishlist with one product
    cy.visit('/detail/9cce06f9dc424875916c43f68b2fd33d');
    cy.get('button').contains('Add to wishlist').click();
    cy.get('.modal').within(() => {
      cy.get('button').contains('Create Guest Wishlist').click();
    });
    
    // Store the guest wishlist token
    cy.getCookie('guest-wishlist-token').then((cookie) => {
      const token = cookie.value;
      
      // Clear cookies and local storage to simulate a new browser session
      cy.clearCookies();
      cy.clearLocalStorage();
      
      // Set the guest wishlist token cookie to simulate returning to the site
      cy.setCookie('guest-wishlist-token', token);
      
      // Visit the guest wishlist page
      cy.visit('/wishlist/guest');
      
      // Verify the product is still in the wishlist
      cy.get('.wishlist-item').should('have.length', 1);
    });
  });
});