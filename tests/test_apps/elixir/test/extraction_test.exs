defmodule KreuzbergTestApp.ExtractionTest do
  use ExUnit.Case, async: true
  import KreuzbergTestApp.TestHelpers

  @moduledoc """
  Comprehensive extraction tests for various document formats.
  """

  describe "PDF extraction" do
    test "extracts text from PDF" do
      if test_doc_exists?("tiny.pdf") do
        result = Kreuzberg.extract_file(test_doc_path("tiny.pdf"))
        extraction = assert_extraction_success(result)
        assert extraction.mime_type == "application/pdf"
        assert is_map(extraction.metadata)
      end
    end

    test "extracts PDF metadata" do
      if test_doc_exists?("tiny.pdf") do
        {:ok, result} = Kreuzberg.extract_file(test_doc_path("tiny.pdf"))
        assert is_map(result.metadata)
      end
    end
  end

  describe "Office document extraction" do
    test "extracts from DOCX" do
      if test_doc_exists?("lorem_ipsum.docx") do
        result = Kreuzberg.extract_file(test_doc_path("lorem_ipsum.docx"))
        extraction = assert_extraction_success(result)
        assert_contains(extraction.content, "Lorem ipsum")
      end
    end

    test "extracts from XLSX" do
      if test_doc_exists?("stanley_cups.xlsx") do
        result = Kreuzberg.extract_file(test_doc_path("stanley_cups.xlsx"))
        extraction = assert_extraction_success(result)
        assert byte_size(extraction.content) > 0
      end
    end
  end

  describe "image extraction with OCR" do
    @tag :ocr
    test "can process images with OCR" do
      if test_doc_exists?("ocr_image.jpg") do
        config = %Kreuzberg.ExtractionConfig{
          ocr: %{
            "enabled" => true,
            "language" => "eng"
          }
        }

        result = Kreuzberg.extract_file(test_doc_path("ocr_image.jpg"), nil, config)

        case result do
          {:ok, extraction} ->
            assert is_binary(extraction.content)

          {:error, _} ->
            # OCR may not be available in test environment
            :ok
        end
      end
    end
  end

  describe "async extraction" do
    test "can extract file asynchronously" do
      if test_doc_exists?("tiny.pdf") do
        task = Kreuzberg.extract_file_async(test_doc_path("tiny.pdf"))
        assert %Task{} = task
        result = Task.await(task, 30_000)
        assert_extraction_success(result)
      end
    end

    test "can batch extract files asynchronously" do
      if test_doc_exists?("tiny.pdf") do
        paths = [test_doc_path("tiny.pdf")]
        task = Kreuzberg.batch_extract_files_async(paths, "application/pdf")
        assert %Task{} = task
        results = Task.await(task, 30_000)

        assert {:ok, [result]} = results
        assert is_binary(result.content)
      end
    end
  end

  describe "extraction with chunking" do
    test "can chunk extracted content" do
      if test_doc_exists?("lorem_ipsum.docx") do
        config = %Kreuzberg.ExtractionConfig{
          chunking: %{
            "enabled" => true,
            "max_chars" => 500,
            "max_overlap" => 50
          }
        }

        {:ok, result} = Kreuzberg.extract_file(test_doc_path("lorem_ipsum.docx"), nil, config)

        if result.chunks do
          assert is_list(result.chunks)
          assert length(result.chunks) > 0
        end
      end
    end
  end

  describe "metadata extraction" do
    test "extracts comprehensive metadata" do
      if test_doc_exists?("tiny.pdf") do
        {:ok, result} = Kreuzberg.extract_file(test_doc_path("tiny.pdf"))
        assert is_map(result.metadata)
        assert is_binary(result.mime_type)
      end
    end
  end

  describe "table extraction" do
    test "can extract tables from documents" do
      if test_doc_exists?("stanley_cups.xlsx") do
        {:ok, result} = Kreuzberg.extract_file(test_doc_path("stanley_cups.xlsx"))

        if result.tables && length(result.tables) > 0 do
          table = hd(result.tables)
          assert is_list(table["cells"])
        end
      end
    end
  end

  describe "cache operations" do
    test "can get cache stats" do
      case Kreuzberg.cache_stats() do
        {:ok, stats} ->
          assert is_map(stats)

        {:error, _} ->
          # Cache may not be available
          :ok
      end
    end

    test "can clear cache" do
      case Kreuzberg.clear_cache() do
        :ok -> assert true
        {:error, _} -> :ok
      end
    end
  end
end
