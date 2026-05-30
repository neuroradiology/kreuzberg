import RustBridge

// MARK: - Property-access ergonomics for e2e tests
//
// This file provides computed-property aliases for methods on swift-bridge-generated types,
// allowing callers to write `result.mimeType` rather than `result.mimeType()`.
// These extensions are especially useful in e2e test assertions where the alef
// fixture generator emits property-access syntax.
//
// Although these are primarily for test convenience, they are part of the public API
// and can be used in production code for more ergonomic access to extraction results.

extension RustBridge.ServerConfigRef {
    /// Computed-property alias for `listen_addr()` method.
    public var listen_addr: String {
        self.listen_addr().toString()
    }
}

// ServerConfigRefMut and ServerConfig inherit the extensions automatically
