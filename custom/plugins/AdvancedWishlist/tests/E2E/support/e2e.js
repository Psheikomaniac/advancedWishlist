// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
// import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Add custom commands for the AdvancedWishlist plugin
Cypress.Commands.add('login', (username = 'customer@example.com', password = 'shopware') => {
  cy.visit('/account/login');
  cy.get('#loginMail').type(username);
  cy.get('#loginPassword').type(password);
  cy.get('button[type="submit"]').contains('Login').click();
  cy.url().should('include', '/account');
});

Cypress.Commands.add('createWishlist', (name = 'Test Wishlist', isPublic = false) => {
  cy.visit('/account/wishlist');
  cy.get('button').contains('Create new wishlist').click();
  cy.get('#wishlistName').type(name);
  if (isPublic) {
    cy.get('#wishlistIsPublic').check();
  }
  cy.get('button[type="submit"]').contains('Create').click();
  cy.contains(name).should('be.visible');
});

Cypress.Commands.add('addProductToWishlist', (productUrl, wishlistName = 'Test Wishlist') => {
  cy.visit(productUrl);
  cy.get('button').contains('Add to wishlist').click();
  cy.get('.wishlist-select').select(wishlistName);
  cy.get('button').contains('Add').click();
  cy.get('.alert-success').should('be.visible');
});

Cypress.Commands.add('removeProductFromWishlist', (wishlistName = 'Test Wishlist', productIndex = 0) => {
  cy.visit('/account/wishlist');
  cy.contains(wishlistName).click();
  cy.get('.wishlist-item').eq(productIndex).find('button').contains('Remove').click();
  cy.get('.alert-success').should('be.visible');
});

Cypress.Commands.add('shareWishlist', (wishlistName = 'Test Wishlist', email = 'friend@example.com') => {
  cy.visit('/account/wishlist');
  cy.contains(wishlistName).click();
  cy.get('button').contains('Share').click();
  cy.get('#shareEmail').type(email);
  cy.get('button[type="submit"]').contains('Share').click();
  cy.get('.alert-success').should('be.visible');
});