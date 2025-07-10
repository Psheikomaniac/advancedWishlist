/// <reference types="cypress" />

describe('Wishlist Management', () => {
  beforeEach(() => {
    // Login before each test
    cy.login();
  });

  it('should create a new wishlist', () => {
    const wishlistName = 'My Test Wishlist';
    
    // Create a new wishlist
    cy.createWishlist(wishlistName);
    
    // Verify the wishlist was created
    cy.contains(wishlistName).should('be.visible');
  });

  it('should add a product to a wishlist', () => {
    const wishlistName = 'Shopping List';
    
    // Create a new wishlist
    cy.createWishlist(wishlistName);
    
    // Add a product to the wishlist
    cy.addProductToWishlist('/detail/9cce06f9dc424875916c43f68b2fd33d', wishlistName);
    
    // Navigate to the wishlist
    cy.visit('/account/wishlist');
    cy.contains(wishlistName).click();
    
    // Verify the product was added
    cy.get('.wishlist-item').should('have.length.at.least', 1);
  });

  it('should remove a product from a wishlist', () => {
    const wishlistName = 'Temporary List';
    
    // Create a new wishlist
    cy.createWishlist(wishlistName);
    
    // Add a product to the wishlist
    cy.addProductToWishlist('/detail/9cce06f9dc424875916c43f68b2fd33d', wishlistName);
    
    // Remove the product from the wishlist
    cy.removeProductFromWishlist(wishlistName);
    
    // Verify the product was removed
    cy.get('.wishlist-item').should('have.length', 0);
  });

  it('should update wishlist details', () => {
    const originalName = 'Original Wishlist';
    const updatedName = 'Updated Wishlist';
    
    // Create a new wishlist
    cy.createWishlist(originalName);
    
    // Navigate to the wishlist
    cy.visit('/account/wishlist');
    cy.contains(originalName).click();
    
    // Click edit button
    cy.get('button').contains('Edit').click();
    
    // Update the wishlist name
    cy.get('#wishlistName').clear().type(updatedName);
    
    // Make it public
    cy.get('#wishlistIsPublic').check();
    
    // Save changes
    cy.get('button[type="submit"]').contains('Save').click();
    
    // Verify the wishlist was updated
    cy.contains(updatedName).should('be.visible');
    cy.get('.badge').contains('Public').should('be.visible');
  });

  it('should share a wishlist', () => {
    const wishlistName = 'Shared Wishlist';
    const recipientEmail = 'friend@example.com';
    
    // Create a new public wishlist
    cy.createWishlist(wishlistName, true);
    
    // Share the wishlist
    cy.shareWishlist(wishlistName, recipientEmail);
    
    // Verify the success message
    cy.get('.alert-success').should('contain', 'Wishlist shared successfully');
  });

  it('should delete a wishlist', () => {
    const wishlistName = 'Wishlist to Delete';
    
    // Create a new wishlist
    cy.createWishlist(wishlistName);
    
    // Navigate to the wishlist
    cy.visit('/account/wishlist');
    cy.contains(wishlistName).click();
    
    // Click delete button
    cy.get('button').contains('Delete').click();
    
    // Confirm deletion
    cy.get('.modal').within(() => {
      cy.get('button').contains('Delete').click();
    });
    
    // Verify the wishlist was deleted
    cy.contains(wishlistName).should('not.exist');
  });
});