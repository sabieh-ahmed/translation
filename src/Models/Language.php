<?php namespace Waavi\Translation\Models;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function __construct(){
        if(!Schema::hasTable('translator_languages')){
            $this->createSchema();
        }
    }

    /**
     *  Table name in the database.
     *  @var string
     */
    protected $table = 'translator_languages';

    /**
     *  List of variables that cannot be mass assigned
     *  @var array
     */
    protected $fillable = ['locale', 'name'];

    /**
     *  Each language may have several translations.
     */
    public function translations()
    {
        return $this->hasMany(Translation::class, 'locale', 'locale');
    }

    /**
     *  Returns the name of this language in the current selected language.
     *
     *  @return string
     */
    public function getLanguageCodeAttribute()
    {
        return "languages.{$this->locale}";
    }

    private function createSchema()
    {
        Schema::create('translator_languages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale', 10)->unique();
            $table->string('name', 60)->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

}
