\# Modular Monolith Boundaries



The Order module must not import User or Product Eloquent models.



Order accesses products through:



App\\Contracts\\Catalog\\ProductCatalog



Order accesses user data through:



App\\Contracts\\Identity\\UserDirectory



Product persistence belongs to the Product module.



User persistence belongs to the User module.



Order persistence belongs to the Order module.



Foreign keys remain database-level integrity constraints.



