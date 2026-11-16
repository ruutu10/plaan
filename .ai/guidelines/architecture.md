## Preferred architecture

- Use HTTP Response classes to compose API response data structures instead of building this inline into the controller
- Use Event/Listener pattern to trigger on significant events, instead of invoking an Action class 
- If a model or a resource needs a controller, do not create two separate controllers (ie ShowController and ShowAdminController) for public vs admin use - they are the same resource, so only one controller should be created, with permission gates to determine what information is returned
