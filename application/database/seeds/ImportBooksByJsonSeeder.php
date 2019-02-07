<?php

use Illuminate\Database\Seeder;

use App\Domain\Books\Entities\BookEntity as Book;
use App\Domain\Authors\Entities\AuthorEntity as Author;
use App\Domain\Disciplines\Entities\DisciplineEntity as Discipline;
use App\Domain\Levels\Entities\LevelEntity as Level;

class ImportBooksByJsonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->importByJsonFile();
    }

    private function importByJsonFile()
    {
        $contents = Storage::disk('local')->get('data_import/origin-data.json');
        $this->importDataFromJsonDecoded(json_decode($contents, true));
    }
    
    private function importDataFromJsonDecoded(array $jsonData)
    {
        try {
            \DB::beginTransaction();
            foreach ($jsonData as $item) {
                $this->importBook($item);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::emergency('um erro aconteceu durante o Seed: '.$e->getMessage());
        }
    }

    private function importBook(array $book)
    {
       $levelInstance = $this->importLevel($book['level']);
       
       $bookInstance = Book::firstOrCreate(
           [ 'isbn' => $book['isbn'] ],
           [
            'title' => $book['title'],
            'cover' => $book['cover'],
            'price' => number_format($book['price'], 2, '.', ''),
            'level_id' => $levelInstance->id,
           ]
        );
        
        $this->importAuthorsSync($book['author'], $bookInstance);
        $this->importDisciplinesSync($book['discipline'], $bookInstance);
        
    }

    private function importAuthorsSync(array $authors, Book $book)
    {
        $authorsId = [];
        if (empty($authors)) {
            return null;
        }
        foreach ($authors as $author) {
            $authorsId[] = $this->importAuthor($author)->id;
        }
        $book->authors()->sync($authorsId);
    }

    private function importAuthor(string $authorName)
    {
       return Author::firstOrCreate(
            [ 'name' => $authorName ]
        );
    }

    private function importDisciplinesSync(array $disciplines, Book $book)
    {
        $disciplinesId = [];
        foreach ($disciplines as $discipline) {
            $disciplinesId[] = $this->importDiscipline($discipline)->id;
        }
        $book->disciplines()->sync($disciplinesId);
    }

    private function importDiscipline(string $disciplineName)
    {
       return Discipline::firstOrCreate(
           [ 'name' => $disciplineName ,
             'code' => $this->removeSpecialChar($disciplineName),
           ]
        );
    }

    private function importLevel(string $levelName)
    {
       return Level::firstOrCreate(
           [ 'name' => $levelName ,
             'code' => $this->removeSpecialChar($levelName),
           ]
        );
    }

    private function removeSpecialChar($value)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/s', '', $value);
    }
}
