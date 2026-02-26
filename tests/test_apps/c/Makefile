# Kreuzberg C FFI Test App - Makefile
#
# Builds and runs the comprehensive C FFI test suite.
#
# Usage:
#   make              Build the test binary
#   make test         Build and run tests
#   make clean        Remove build artifacts
#
# Configuration:
#   KREUZBERG_ROOT    Path to kreuzberg repo root (default: ../../..)
#   CC                C compiler (default: cc)
#   BUILD_MODE        debug or release (default: release)

KREUZBERG_ROOT ?= ../../..
BUILD_MODE ?= release

HEADER_DIR = $(KREUZBERG_ROOT)/crates/kreuzberg-ffi
LIBDIR = $(KREUZBERG_ROOT)/target/$(BUILD_MODE)
LIBNAME = kreuzberg_ffi

CC ?= cc
CFLAGS = -std=c11 -Wall -Wextra -Wpedantic -I$(HEADER_DIR)

# Try pkg-config first, fall back to manual paths
PKG_CONFIG_PATH_EXTRA = $(KREUZBERG_ROOT)/crates/kreuzberg-ffi
HAVE_PKGCONFIG := $(shell PKG_CONFIG_PATH=$(PKG_CONFIG_PATH_EXTRA):$$PKG_CONFIG_PATH pkg-config --exists kreuzberg-ffi 2>/dev/null && echo yes || echo no)

ifeq ($(HAVE_PKGCONFIG),yes)
  PKG_CFLAGS := $(shell PKG_CONFIG_PATH=$(PKG_CONFIG_PATH_EXTRA):$$PKG_CONFIG_PATH pkg-config --cflags kreuzberg-ffi)
  PKG_LIBS := $(shell PKG_CONFIG_PATH=$(PKG_CONFIG_PATH_EXTRA):$$PKG_CONFIG_PATH pkg-config --libs kreuzberg-ffi)
  CFLAGS += $(PKG_CFLAGS)
  LDFLAGS = $(PKG_LIBS)
  RPATH =
  $(info Using pkg-config for kreuzberg-ffi)
else
  # Manual path configuration
  ifeq ($(shell uname),Darwin)
    LDFLAGS = -L$(LIBDIR) -l$(LIBNAME) -framework CoreFoundation -framework Security -lpthread
    RPATH = -Wl,-rpath,$(LIBDIR)
  else ifeq ($(OS),Windows_NT)
    LDFLAGS = -L$(LIBDIR) -l$(LIBNAME) -lws2_32 -luserenv -lbcrypt
    RPATH =
  else
    LDFLAGS = -L$(LIBDIR) -l$(LIBNAME) -lpthread -ldl -lm
    RPATH = -Wl,-rpath,$(LIBDIR)
  endif
  $(info Using manual paths (HEADER_DIR=$(HEADER_DIR), LIBDIR=$(LIBDIR)))
endif

BINARY = kreuzberg_test

.PHONY: all build test clean help

all: build

build: $(BINARY)

$(BINARY): main.c
	$(CC) $(CFLAGS) $(RPATH) -o $@ main.c $(LDFLAGS)

test: build
	@echo "--- Running Kreuzberg C FFI Test Suite ---"
	./$(BINARY)

clean:
	rm -f $(BINARY)

help:
	@echo "Kreuzberg C FFI Test App\n\nTargets: build (default), test, clean, help\nVariables: KREUZBERG_ROOT (default: ../../..), CC (default: cc), BUILD_MODE (default: release)\nPrereqs: cargo build --release -p kreuzberg-ffi && ensure test_documents/ exists\nExample: cargo build --release -p kreuzberg-ffi && make test"
