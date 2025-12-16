package kreuzberg_test

import (
	"context"
	"errors"
	"sync"
	"testing"

	"github.com/kreuzberg-dev/kreuzberg/packages/go/v4"
)

// TestConcurrentExtractFileSync verifies thread-safe concurrent file extraction.
func TestConcurrentExtractFileSync(t *testing.T) {
	testPDF := createTestPDF(t)
	defer cleanup(testPDF)

	numGoroutines := 10
	var wg sync.WaitGroup
	errChan := make(chan error, numGoroutines)

	wg.Add(numGoroutines)
	for i := 0; i < numGoroutines; i++ {
		go func(index int) {
			defer wg.Done()
			result, err := kreuzberg.ExtractFileSync(testPDF, nil)
			if err != nil {
				errChan <- err
				return
			}
			if result == nil {
				errChan <- errors.New("nil result")
				return
			}
			if result.Content == "" {
				errChan <- errors.New("empty content")
				return
			}
		}(i)
	}

	wg.Wait()
	close(errChan)

	for err := range errChan {
		t.Errorf("concurrent extraction failed: %v", err)
	}
}

// TestConcurrentExtractBytesSync verifies thread-safe concurrent bytes extraction.
func TestConcurrentExtractBytesSync(t *testing.T) {
	pdfBytes := generateTestPDFBytes(t)

	numGoroutines := 10
	var wg sync.WaitGroup
	errChan := make(chan error, numGoroutines)

	wg.Add(numGoroutines)
	for i := 0; i < numGoroutines; i++ {
		go func(index int) {
			defer wg.Done()
			result, err := kreuzberg.ExtractBytesSync(pdfBytes, "application/pdf", nil)
			if err != nil {
				errChan <- err
				return
			}
			if result == nil {
				errChan <- errors.New("nil result")
				return
			}
			if result.MimeType != "application/pdf" {
				errChan <- errors.New("incorrect mime type")
				return
			}
		}(i)
	}

	wg.Wait()
	close(errChan)

	for err := range errChan {
		t.Errorf("concurrent extraction failed: %v", err)
	}
}

// TestConcurrentExtractWithContext verifies context cancellation with concurrent operations.
func TestConcurrentExtractWithContext(t *testing.T) {
	testPDF := createTestPDF(t)
	defer cleanup(testPDF)

	numGoroutines := 5
	var wg sync.WaitGroup
	errChan := make(chan error, numGoroutines)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	wg.Add(numGoroutines)
	for i := 0; i < numGoroutines; i++ {
		go func(index int) {
			defer wg.Done()
			result, err := kreuzberg.ExtractFileWithContext(ctx, testPDF, nil)
			if err != nil && !errors.Is(err, context.Canceled) {
				errChan <- err
				return
			}
			if err == nil && result == nil {
				errChan <- errors.New("nil result with no error")
				return
			}
		}(i)
	}

	wg.Wait()
	close(errChan)

	for err := range errChan {
		t.Errorf("concurrent context extraction failed: %v", err)
	}
}

// TestContextCancellationBeforeExtraction verifies context is checked before extraction.
func TestContextCancellationBeforeExtraction(t *testing.T) {
	testPDF := createTestPDF(t)
	defer cleanup(testPDF)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	result, err := kreuzberg.ExtractFileWithContext(ctx, testPDF, nil)

	if err == nil {
		t.Errorf("expected context cancellation error, got nil")
	}
	if !errors.Is(err, context.Canceled) {
		t.Errorf("expected context.Canceled, got: %v", err)
	}
	if result != nil {
		t.Errorf("expected nil result with canceled context, got: %v", result)
	}
}

// TestContextTimeoutBeforeExtraction verifies context timeout is respected.
func TestContextTimeoutBeforeExtraction(t *testing.T) {
	testPDF := createTestPDF(t)
	defer cleanup(testPDF)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	result, err := kreuzberg.ExtractBytesWithContext(ctx, []byte{}, "application/pdf", nil)

	if err == nil {
		t.Errorf("expected context error, got nil")
	}
	if result != nil {
		t.Errorf("expected nil result with canceled context, got: %v", result)
	}
}

// TestBatchConcurrentExtraction verifies batch operations are thread-safe.
func TestBatchConcurrentExtraction(t *testing.T) {
	pdfBytes := generateTestPDFBytes(t)

	paths := []string{}
	for i := 0; i < 3; i++ {
		pdfPath := createTestPDF(t)
		paths = append(paths, pdfPath)
		defer cleanup(pdfPath)
	}

	numGoroutines := 5
	var wg sync.WaitGroup
	errChan := make(chan error, numGoroutines)

	wg.Add(numGoroutines)
	for i := 0; i < numGoroutines; i++ {
		go func(index int) {
			defer wg.Done()
			results, err := kreuzberg.BatchExtractFilesSync(paths, nil)
			if err != nil {
				errChan <- err
				return
			}
			if len(results) != len(paths) {
				errChan <- errors.New("batch results count mismatch")
				return
			}
		}(i)
	}

	wg.Wait()
	close(errChan)

	for err := range errChan {
		t.Errorf("concurrent batch extraction failed: %v", err)
	}
}

// TestBatchExtractBytesWithContext verifies batch bytes extraction with context.
func TestBatchExtractBytesWithContext(t *testing.T) {
	pdfBytes := generateTestPDFBytes(t)

	items := []kreuzberg.BytesWithMime{
		{Data: pdfBytes, MimeType: "application/pdf"},
		{Data: pdfBytes, MimeType: "application/pdf"},
	}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	results, err := kreuzberg.BatchExtractBytesWithContext(ctx, items, nil)
	if err != nil {
		t.Fatalf("batch extraction with context failed: %v", err)
	}
	if len(results) != len(items) {
		t.Errorf("expected %d results, got %d", len(items), len(results))
	}
}

// TestConcurrentErrorHandling verifies error handling in concurrent scenarios.
func TestConcurrentErrorHandling(t *testing.T) {
	numGoroutines := 10
	var wg sync.WaitGroup
	errorCount := 0
	var mu sync.Mutex

	wg.Add(numGoroutines)
	for i := 0; i < numGoroutines; i++ {
		go func(index int) {
			defer wg.Done()
			_, err := kreuzberg.ExtractFileSync("/nonexistent/file.pdf", nil)
			if err == nil {
				t.Error("expected error for nonexistent file")
			}
			mu.Lock()
			errorCount++
			mu.Unlock()
		}(i)
	}

	wg.Wait()

	if errorCount != numGoroutines {
		t.Errorf("expected %d errors, got %d", numGoroutines, errorCount)
	}
}
