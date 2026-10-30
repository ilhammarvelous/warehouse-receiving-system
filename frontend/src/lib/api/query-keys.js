export const queryKeys = {
  products: ["products"],
  productList: (filters) => ["products", "list", filters],
  documents: ["receiving-documents"],
  document: (id) => ["receiving-documents", "detail", String(id)],
  documentList: (filters) => ["receiving-documents", "list", filters],
  inventory: ["inventory"],
  inventoryList: (filters) => ["inventory", "list", filters],
  transactions: ["inventory-transactions"],
  transactionList: (filters) => ["inventory-transactions", "list", filters],
};
