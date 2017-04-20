<?php namespace Waavi\Translation\Models;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    public function __construct(){
        if(!Schema::hasTable('translator_translations')){
            $this->createSchema();
        }
    }

    /**
     *  Table name in the database.
     *  @var string
     */
    protected $table = 'translator_translations';

    /**
     *  List of variables that can be mass assigned
     *  @var array
     */
    protected $fillable = ['locale', 'namespace', 'group', 'item', 'text', 'unstable'];

    /**
     *  Each translation belongs to a language.
     */
    public function language()
    {
        return $this->belongsTo(Language::class, 'locale', 'locale');
    }

    /**
     *  Returns the full translation code for an entry: namespace.group.item
     *  @return string
     */
    public function getCodeAttribute()
    {
        return $this->namespace === '*' ? "{$this->group}.{$this->item}" : "{$this->namespace}::{$this->group}.{$this->item}";
    }

    /**
     *  Flag this entry as Reviewed
     *  @return void
     */
    public function flagAsReviewed()
    {
        $this->unstable = 0;
    }

    /**
     *  Set the translation to the locked state
     *  @return void
     */
    public function lock()
    {
        $this->locked = 1;
    }

    /**
     *  Check if the translation is locked
     *  @return boolean
     */
    public function isLocked()
    {
        return (boolean) $this->locked;
    }

    private function createSchema(){
        Schema::create('translator_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('locale', 10);
            $table->string('namespace', 150)->default('*');
            $table->string('group', 150);
            $table->string('item', 150);
            $table->text('text');
            $table->boolean('unstable')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->foreign('locale')->references('locale')->on('translator_languages');
            $table->unique(['locale', 'namespace', 'group', 'item']);
        });
    }
}
